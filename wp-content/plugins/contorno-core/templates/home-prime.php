<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<section class="contorno-section contorno-home-prime" aria-label="CTN Prime">
  <div class="site-container"><div class="contorno-home-prime__card">
    <div class="contorno-home-prime__copy">
      <p class="eyebrow">Exclusivo</p>
      <h2>CTN <span>Prime</span></h2>
      <p class="contorno-home-prime__subtitle">Centro de Treinamento Contorno</p>
      <p class="contorno-home-prime__text">SEU ÚNICO LIMITE É VOCÊ MESMO! O maior e mais completo Centro de Treinamento espera por você.</p>
      <ul class="contorno-home-prime__features"><li><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.596 12.768a2 2 0 1 0 2.829-2.829l-1.768-1.767a2 2 0 0 0 2.828-2.829l-2.828-2.828a2 2 0 0 0-2.829 2.828l-1.767-1.768a2 2 0 1 0-2.829 2.829z"></path><path d="m2.5 21.5 1.4-1.4"></path><path d="m20.1 3.9 1.4-1.4"></path><path d="M5.343 21.485a2 2 0 1 0 2.829-2.828l1.767 1.768a2 2 0 1 0 2.829-2.829l-6.364-6.364a2 2 0 1 0-2.829 2.829l1.768 1.767a2 2 0 0 0-2.828 2.829z"></path><path d="m9.6 14.4 4.8-4.8"></path></svg><span>Equipamentos Premium</span></li><li><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><path d="M16 3.128a4 4 0 0 1 0 7.744"></path><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><circle cx="9" cy="7" r="4"></circle></svg><span>Personal Especializado</span></li><li><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path></svg><span>Ambiente Exclusivo</span></li><li><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 7h6v6"></path><path d="m22 7-8.5 8.5-5-5L2 17"></path></svg><span>Resultados Extraordinários</span></li></ul>
      <?php echo contorno_button( (string) $a['cta_label'], $url, 'primary', array( 'class' => 'cta-label' ) ); ?>
    </div>
    <div class="contorno-home-prime__media">
      <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) $a['image_alt'] ); ?>" loading="lazy" decoding="async" />
    </div>
  </div></div>
</section>
