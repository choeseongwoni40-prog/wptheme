<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    
    <!-- 광고 스크립트 (Head) -->
    <!-- 광고 스크립트 없음 -->

    <!-- 사용자 정의 코드 -->
    <meta name='admaven-placement' content=Bqjw8qdg4>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- 헤더 -->
<header class="header" id="header">
    <div class="container">
        <div class="logo">
            <?php if (has_custom_logo()): ?>
                <?php the_custom_logo(); ?>
            <?php else: ?>
                <img src="https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEhwxd_YGfZiM_d9LPozylA_vt2w36-eanzKSgvMQm2zkh-s41pKzT2FDyyqB9cz713Tm3nRFVbtRR8GGXlEQh7UDr4BDteEwfQ_JDV0Yl_xYA5uBGWrqyhDLH_PNEa9cJmNLOhhFc7XKAJChRiR9_6KZbraUo8FpA2IGMxbgMNGAtnoi-WlBnWYpnm0FKw/w945-h600-p-k-no-nu/img.png" alt="<?php bloginfo('name'); ?>">
            <?php endif; ?>
        </div>
        <h1 class="logo-text"><?php echo esc_html('정부지원금'); ?></h1>
    </div>
</header>
