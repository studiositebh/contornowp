<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<section class="contorno-section contorno-home-app" aria-label="App Contorno do Corpo">
  <div class="site-container"><div class="contorno-home-app__card">
    <div class="contorno-home-app__phones"><img src="<?php echo esc_url( contorno_core_url( 'assets/img/home/app-phones.png' ) ); ?>" alt="Aplicativo Contorno do Corpo nos celulares" loading="lazy" decoding="async" /></div>
    <div class="contorno-home-app__copy">
      <p class="eyebrow">App Contorno do Corpo</p>
      <h2>Sua academia no seu ritmo</h2>
      <p class="contorno-home-app__text">O app Contorno do Corpo é o seu aliado para treinar melhor, acompanhar resultados e ficar por dentro de tudo.</p>
      <ul class="contorno-home-app__benefits"><li><span aria-hidden="true">✓</span>Acesso aos seus treinos e evolução</li><li><span aria-hidden="true">✓</span>Horários de aulas em tempo real</li><li><span aria-hidden="true">✓</span>Check-in rápido nas unidades</li><li><span aria-hidden="true">✓</span>Desafios, recompensas e muito mais</li></ul>
    </div>
    <div class="contorno-home-app__download">
      <img class="contorno-home-app__qr" src="<?php echo esc_url( contorno_core_url( 'assets/img/home/app-qr.png' ) ); ?>" alt="QR Code para baixar o aplicativo Contorno do Corpo" loading="lazy" decoding="async" />
      <div><p class="contorno-home-app__download-title">Baixe agora!</p><p>Escaneie o QR Code<br />e baixe nosso app.</p><div class="contorno-home-app__stores">
<?php if ( '' !== esc_url( (string) $a['google_play'] ) ) : ?><a class="contorno-home-app__badge" href="<?php echo esc_url( (string) $a['google_play'] ); ?>"><?php else : ?><span class="contorno-home-app__badge"><?php endif; ?><img src="<?php echo esc_url( contorno_core_url( 'assets/img/home/google-play.png' ) ); ?>" alt="Disponível no Google Play" loading="lazy" decoding="async" /><?php if ( '' !== esc_url( (string) $a['google_play'] ) ) : ?></a><?php else : ?></span><?php endif; ?>
<?php if ( '' !== esc_url( (string) $a['app_store'] ) ) : ?><a class="contorno-home-app__badge" href="<?php echo esc_url( (string) $a['app_store'] ); ?>"><?php else : ?><span class="contorno-home-app__badge"><?php endif; ?><img src="<?php echo esc_url( contorno_core_url( 'assets/img/home/app-store.png' ) ); ?>" alt="Disponível na App Store" loading="lazy" decoding="async" /><?php if ( '' !== esc_url( (string) $a['app_store'] ) ) : ?></a><?php else : ?></span><?php endif; ?>
</div></div>
    </div>
  </div></div>
</section>
