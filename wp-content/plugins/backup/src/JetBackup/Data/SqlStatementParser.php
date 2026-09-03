<?php

namespace JetBackup\Data;

if (!defined( '__JETBACKUP__')) die('Direct access is not allowed');

/**
 * Splits a SQL dump into single statements without being fooled by quoted text.
 *
 * A ';' or a word like CREATE / VIEW / DEFINER= only counts when it's real SQL, not when it sits
 * inside a quoted value. That is the whole point: text a user typed (a wp_users display_name, a
 * profile URL) must never be read as SQL. So we keep track of whether we're inside a string, an
 * identifier or a comment, and only react to characters that are actually SQL.
 *
 * It runs as a stream: give it the dump one line at a time and it hands back each finished statement
 * when its real ';' shows up, remembering where it was between calls. That lets the importer keep
 * reading the file line by line (low memory, resumable) like before.
 *
 * No database or WordPress code in here on purpose, so it can be tested on its own.
 */
class SqlStatementParser {

	// scanner modes
	private const M_NORMAL        = 0;
	private const M_SQUOTE        = 1; // inside '...'
	private const M_DQUOTE        = 2; // inside "..."
	private const M_BACKTICK      = 3; // inside `...`
	private const M_LINE_COMMENT  = 4; // -- ... or # ...   (thrown away)
	private const M_BLOCK_COMMENT = 5; // /* ... */          (thrown away)
	private const M_COND_COMMENT  = 6; // /*! ... */         (kept - MySQL runs it)

	// We only read the start of a statement to find its first word. A header is tiny; an INSERT can be huge.
	private const HEAD_WINDOW = 1000;

	private int $mode       = self::M_NORMAL;
	private bool $escape    = false; // last char inside a string was a backslash
	private string $current = '';    // the statement we're building

	/**
	 * Feed one chunk (normally one line, newline included). Returns the statements that finished
	 * (reached their real ';') in this chunk - usually none or one.
	 *
	 * @return string[]
	 */
	public function feed(string $chunk): array {
		$out = [];
		$len = strlen($chunk);

		for ($i = 0; $i < $len; $i++) {
			$ch   = $chunk[$i];
			$next = $i + 1 < $len ? $chunk[$i + 1] : '';

			switch ($this->mode) {

				case self::M_NORMAL:
					if ($ch === "'")  { $this->current .= $ch; $this->mode = self::M_SQUOTE;   break; }
					if ($ch === '"')  { $this->current .= $ch; $this->mode = self::M_DQUOTE;   break; }
					if ($ch === '`')  { $this->current .= $ch; $this->mode = self::M_BACKTICK; break; }

					// '#' always starts a comment; '--' only starts one when a space or line-end follows (MySQL rule)
					if ($ch === '#') { $this->mode = self::M_LINE_COMMENT; break; }
					if ($ch === '-' && $next === '-') {
						$after = $i + 2 < $len ? $chunk[$i + 2] : "\n"; // end of line counts as a space
						if ($after === ' ' || $after === "\t" || $after === "\n" || $after === "\r" || $after === "\0") {
							$this->mode = self::M_LINE_COMMENT;
							$i++;
							break;
						}
						// not a comment (like "a--b") - treat '-' as a normal char
					}

					// /* ... */ - keep it only if it's the /*! ... */ kind that MySQL runs
					if ($ch === '/' && $next === '*') {
						if (($i + 2 < $len ? $chunk[$i + 2] : '') === '!') {
							$this->current .= '/*!';
							$i += 2;
							$this->mode = self::M_COND_COMMENT;
						} else {
							$i++;
							$this->mode = self::M_BLOCK_COMMENT;
						}
						break;
					}

					if ($ch === ';') { // end of a statement
						$this->current .= ';';
						$stmt = trim($this->current);
						$this->current = '';
						if ($stmt !== '' && $stmt !== ';') $out[] = $stmt;
						break;
					}

					$this->current .= $ch;
					break;

				case self::M_SQUOTE:
					$this->current .= $ch;
					if ($this->escape)      { $this->escape = false; break; }
					if ($ch === '\\')       { $this->escape = true;  break; }
					if ($ch === "'") {
						if ($next === "'") { $this->current .= "'"; $i++; break; } // '' is a quote inside the string, keep going
						$this->mode = self::M_NORMAL;
					}
					break;

				case self::M_DQUOTE:
					$this->current .= $ch;
					if ($this->escape)      { $this->escape = false; break; }
					if ($ch === '\\')       { $this->escape = true;  break; }
					if ($ch === '"') {
						if ($next === '"') { $this->current .= '"'; $i++; break; }
						$this->mode = self::M_NORMAL;
					}
					break;

				case self::M_BACKTICK:
					$this->current .= $ch;
					// no backslash escapes in identifiers; a doubled `` is an escaped backtick
					if ($ch === '`') {
						if ($next === '`') { $this->current .= '`'; $i++; break; }
						$this->mode = self::M_NORMAL;
					}
					break;

				case self::M_LINE_COMMENT:
					if ($ch === "\n") $this->mode = self::M_NORMAL; // ends at the newline
					break;

				case self::M_BLOCK_COMMENT:
					if ($ch === '*' && $next === '/') { $i++; $this->mode = self::M_NORMAL; } // ends at */
					break;

				case self::M_COND_COMMENT:
					$this->current .= $ch; // keep the text; ends at */
					if ($ch === '*' && $next === '/') { $this->current .= '/'; $i++; $this->mode = self::M_NORMAL; }
					break;
			}
		}

		return $out;
	}

	/** True while we're in the middle of a statement (not on a clean boundary yet). */
	public function hasPending(): bool {
		return $this->mode !== self::M_NORMAL || trim($this->current) !== '';
	}

	/**
	 * Return a last statement that had no ';' at the end (some dumps skip the final one) and reset.
	 * Returns null when there's nothing left.
	 */
	public function flush(): ?string {
		$stmt = trim($this->current);
		$this->current = '';
		$this->mode    = self::M_NORMAL;
		$this->escape  = false;
		return ($stmt === '' || $stmt === ';') ? null : $stmt;
	}

	/**
	 * Split a whole SQL string in one go (tests, or callers that already hold it all in memory).
	 *
	 * @return string[]
	 */
	public static function split(string $sql): array {
		$p   = new self();
		$out = $p->feed($sql);
		if (($tail = $p->flush()) !== null) $out[] = $tail;
		return $out;
	}

	/**
	 * Does this statement really start with CREATE ... VIEW?
	 *
	 * We only look at the real start - after dropping leading comments and unwrapping mysqldump's
	 * "/*!NNNNN CREATE ... VIEW ... *​/" wrappers - so a CREATE/VIEW that is only text inside an INSERT
	 * value never counts.
	 */
	public static function isCreateView(string $sql): bool {
		$head = self::effectiveHead($sql);
		if ($head === '' || strncasecmp($head, 'CREATE', 6) !== 0) return false; // only a CREATE can be a view

		$prefix = '/^CREATE\s+'
			. '(?:OR\s+REPLACE\s+)?'
			. '(?:ALGORITHM\s*=\s*\w+\s+)?'
			. '(?:DEFINER\s*=\s*(?:`[^`]+`@`[^`]+`|\'[^\']+\'@\'[^\']+\'|"[^"]+"@"[^"]+"|\S+)\s+)?'
			. '(?:SQL\s+SECURITY\s+(?:DEFINER|INVOKER)\s+)?'
			. 'VIEW\b/i';

		return (bool) preg_match($prefix, $head);
	}

	/**
	 * The real start of a statement: a short piece from the front with comments removed and mysqldump's
	 * /*! ... *​/ wrappers unwrapped, so the first real word (CREATE / INSERT / SET / ...) is visible.
	 * Only used to tell statements apart, never to change one, so dropping the rest is fine.
	 */
	public static function effectiveHead(string $sql): string {
		$s = substr($sql, 0, self::HEAD_WINDOW);

		$s = preg_replace('/--(?=[\s]).*?(?:\n|$)/s', ' ', $s);   // drop -- comments
		$s = preg_replace('/#.*?(?:\n|$)/s',          ' ', $s);   // drop # comments
		$s = preg_replace('/\/\*(?!!).*?\*\//s',      ' ', $s);   // drop plain /* */ comments
		$s = preg_replace('/\/\*!\d*/',               ' ', $s);   // unwrap /*! openers (keep their SQL)
		$s = str_replace('*/', ' ', $s);                          // and drop the closers
		$s = preg_replace('/[ \t\r\n]+/', ' ', $s);

		return ltrim((string) $s);
	}

	/**
	 * Rewrite a CREATE VIEW so it restores on another server: drop DEFINER=/ALGORITHM=, force
	 * SQL SECURITY INVOKER, and make it CREATE OR REPLACE. Anything that isn't really a CREATE VIEW is
	 * returned unchanged - the isCreateView() check up front is what keeps it from ever mangling an
	 * INSERT that just happens to mention those words.
	 */
	public static function normalizeCreateView(string $sql): string {
		if (!self::isCreateView($sql)) {
			return $sql;
		}

		$parts  = preg_split('/\bAS\b/i', $sql, 2);
		$header = $parts[0] ?? $sql;
		$body   = $parts[1] ?? '';

		$header = preg_replace(
			'/\/\*!\d+\s+DEFINER\s*=\s*[^*]+SQL\s+SECURITY\s+(?:DEFINER|INVOKER)\s*\*\//i',
			' ',
			$header
		);

		$header = preg_replace(
			'/\bDEFINER\s*=\s*(?:`[^`]+`@`[^`]+`|\'[^\']+\'@\'[^\']+\'|[^ \t\n\r\f\)]+)\s*/i',
			' ',
			$header
		);

		$header = preg_replace('/\bALGORITHM\s*=\s*\w+\s*/i', ' ', $header);

		$header = preg_replace('/\bCREATE\s+(?!OR\s+REPLACE\b)/i', 'CREATE OR REPLACE ', $header, 1);

		if (preg_match('/\bSQL\s+SECURITY\s+(?:DEFINER|INVOKER)\b/i', $header)) {
			$header = preg_replace('/\bSQL\s+SECURITY\s+(?:DEFINER|INVOKER)\b/i', 'SQL SECURITY INVOKER', $header, 1);
		} else {
			$header = preg_replace(
				'/\b(CREATE\s+(?:OR\s+REPLACE\s+)?)(VIEW\b)/i',
				'$1SQL SECURITY INVOKER $2',
				$header,
				1
			);
		}

		$header = preg_replace('/[ \t]+/', ' ', $header);
		$header = trim($header);

		if ($body === '') {
			return $header;
		}
		return $header . ' AS' . (preg_match('/^\s/', $body) ? '' : ' ') . $body;
	}
}
