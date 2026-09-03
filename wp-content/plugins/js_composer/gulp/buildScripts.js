const path = require( 'path' );
const { src, dest, series, parallel } = require( 'gulp' );
const plumber = require( 'gulp-plumber' );
const sourcemaps = require( 'gulp-sourcemaps' );
const uglify = require( 'gulp-uglify' );
const concat = require( 'gulp-concat' );
const mode = require( 'gulp-mode' )();
const buildConfig = require( '../build-config.json' );
const rename = require( 'gulp-rename' );
const fs = require( 'fs' );

function errorHandler ( err ) {
	console.error( err );
	this.emit( 'end' ); // Continue the task stream
}

function buildJs ( done, files, srcPath, destPath ) {
	files = files || buildConfig.uglifyList;
	srcPath = srcPath || buildConfig.globalOptions.js.srcPath;
	destPath = destPath ? path.join( destPath ) : buildConfig.globalOptions.js.destPath;
	const tasks = files.map( ( entry ) => {
		return function buildingJsFiles () {
			return src( entry.src.map( ( src ) => {
				return srcPath + src;
			}) )
				.pipe( plumber( errorHandler ) )
				.pipe( mode.development( sourcemaps.init() ) )
				.pipe( ( mode.production( uglify() ) ) )
				.pipe( concat( entry.dest ) )
				.pipe( mode.development( sourcemaps.write() ) )
				.pipe( dest( destPath ) );
		};
	});
	// Execute tasks in series
	series( tasks )( ( error ) => {
		if ( error ) {
			done( error );
		} else {
			done();
		}
	});
}

function buildJsPackages () {
	const jsTasks = buildConfig.nodeModules.js.map( ( file ) => {
		return function buildingJsPackages () {
			return src( path.join( buildConfig.nodeModules.srcPath, file.src ) )
				.pipe( uglify() )
				.pipe( rename({ suffix: '.min' }) )
				.pipe( dest( path.join( buildConfig.nodeModules.destPath, file.dest ) ) );
		};
	});

	return series.apply( null, jsTasks );
}

/**
 * Copies pre-minified files and assets from node_modules to dist.
 * 1. copyFiles: Pre-minified JS/CSS or files needing rename
 * 2. assets: Fonts, images, directories - copied as-is
 */
function copyNodeModules ( done ) {
	const tasks = [];

	// Copy pre-minified or special files that don't need processing
	if ( buildConfig.nodeModules.copyFiles ) {
		buildConfig.nodeModules.copyFiles.forEach( ( file ) => {
			tasks.push( function copyNodeModuleFile () {
				const srcPath = path.join( buildConfig.nodeModules.srcPath, file.src );
				const destPath = path.join( buildConfig.nodeModules.destPath, file.dest );

				// Just copy the file, optionally with a new name
				if ( file.outputName ) {
					return src( srcPath, { allowEmpty: true })
						.pipe( rename( file.outputName ) )
						.pipe( dest( destPath ) );
				} else {
					return src( srcPath, { allowEmpty: true })
						.pipe( dest( destPath ) );
				}
			});
		});
	}

	// Copy asset directories and files (fonts, images, etc.)
	if ( buildConfig.nodeModules.assets ) {
		buildConfig.nodeModules.assets.forEach( assetPath => {
			tasks.push( function copyAsset () {
				const srcPath = path.join( buildConfig.nodeModules.srcPath, assetPath );

				// Handle different asset patterns
				if ( assetPath.endsWith( '/' ) ) {
					// Directory - copy entire directory
					const destDir = path.join( buildConfig.nodeModules.destPath, assetPath );
					return src( path.join( srcPath, '**/*' ), { allowEmpty: true, encoding: false })
						.pipe( dest( destDir ) );
				} else {
					// Single file
					const destDir = path.join( buildConfig.nodeModules.destPath, path.dirname( assetPath ) );
					return src( srcPath, { allowEmpty: true, encoding: false })
						.pipe( dest( destDir ) );
				}
			});
		});
	}

	// If no tasks, just complete
	if ( tasks.length === 0 ) {
		return done();
	}

	// Use parallel instead of series for better performance
	return parallel.apply( null, tasks )( done );
}

function buildModuleJsFiles ( done ) {
	buildJs( done,
		buildConfig.modules.moduleUglifyList,
		buildConfig.modules.srcPath,
		buildConfig.modules.srcPath
	);
}

function buildModuleJsMainFile ( done ) {
	buildJs(
		done,
		buildConfig.modulesMainFile.modulesMainFileUglifyList,
		buildConfig.modulesMainFile.srcPath,
		buildConfig.modulesMainFile.destPath
	);
}

/**
 * Recursively processes all JavaScript files within a given directory and its subdirectories.
 * @param {string} directoryPath - The path of the directory to be processed.
 * @param {function} done - Callback function to be called when processing is complete.
 */
function processJsFilesInDirectory ( directoryPath, done ) {
	fs.readdir( directoryPath, ( err, files ) => {
		if ( err ) {
			console.error( 'Error:', err );
			return done( err );
		}

		let pending = files.length;

		if ( pending === 0 ) {
			return done(); // If no files, signal completion immediately
		}

		files.forEach( ( file ) => {
			const filePath = path.join( directoryPath, file );
			fs.stat( filePath, ( err, stats ) => {
				if ( err ) {
					console.error( 'Error:', err );
					done( err );
					return;
				}

				if ( stats.isDirectory() ) {
					// If the current item is a directory, recursively process it
					processJsFilesInDirectory( filePath, ( err ) => {
						if ( --pending === 0 ) { done( err ); } // Call done only when all recursive calls are done
					});
				} else if ( path.extname( file ) === '.js' && !file.endsWith( '.min.js' ) ) {
					processJsFile( filePath, () => {
						if ( --pending === 0 ) {
							done();
						}
					});
				} else {
					if ( --pending === 0 ) { done(); } // Call done if no further processing is needed
				}
			});
		});
	});
}

/**
 * Minifies a single JS file to .min.js in same folder.
 * @param {string} filePath - JS file path
 * @param {function} done - Completion callback
 */
function processJsFile ( filePath, done ) {
	const destPath = path.dirname( filePath );
	const fileNameWithoutExtension = path.basename( filePath, '.js' );
	const destFileName = fileNameWithoutExtension + '.min.js';

	src( filePath )
		.pipe( plumber( errorHandler ) )
		.pipe( mode.development( sourcemaps.init() ) )
		.pipe( mode.production( uglify() ) )
		.pipe( mode.development( sourcemaps.write() ) )
		.pipe( rename( destFileName ) )
		.pipe( dest( destPath ) )
		.on( 'end', done );
}

function buildJsLibs ( done ) {
	const srcPath = buildConfig.globalOptions.jsLibs.srcPath;
	processJsFilesInDirectory( srcPath, done );
}

/* eslint-disable */
module.exports = {
	buildJs,
	buildJsPackages,
	copyNodeModules,
	buildModuleJsFiles,
	buildModuleJsMainFile,
	buildJsLibs
};
/* eslint-enable */
