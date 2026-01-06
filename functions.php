<?php
/**
 * Theme Functions - 지원금 수익화 스킨 (최종 완성형)
 * 광고 자동 개조 + 링크 버튼화 + 텍스트 카드화 + 메타 카드 자동생성
 */

// ==================== 기본 테마 설정 ====================
function support_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('custom-logo');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'gallery', 'caption'));
}
add_action('after_setup_theme', 'support_theme_setup');

// ==================== 스크립트 및 스타일 로드 ====================
function support_enqueue_scripts() {
    wp_enqueue_style('support-style', get_stylesheet_uri(), array(), '1.1');
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'support_enqueue_scripts');

// ==================== 관리자 메뉴 (광고 설정) ====================
function support_admin_menu() {
    add_menu_page('광고 설정', '광고 관리', 'manage_options', 'support-ads', 'support_ads_page', 'dashicons-money-alt', 20);
}
add_action('admin_menu', 'support_admin_menu');

// ==================== 광고 설정 페이지 ====================
function support_ads_page() {
    if (isset($_POST['save_ads']) && check_admin_referer('support_save_ads')) {
        $ad_code = stripslashes($_POST['ad_code']);
        $processed = support_process_ad_code($ad_code);
        
        update_option('support_ad_settings', array(
            'original_code' => $ad_code,
            'anchor_code' => $processed['anchor'],
            'interstitial_code' => $processed['interstitial'],
            'manual_code' => $processed['manual'],
            'delay_seconds' => intval($_POST['delay_seconds']),
            'enable_anchor' => isset($_POST['enable_anchor']),
            'enable_interstitial' => isset($_POST['enable_interstitial'])
        ));
        echo '<div class="notice notice-success"><p>✅ 설정 저장 완료! 네이티브 광고는 <b>[manual_ad]</b> 숏코드를 사용하세요.</p></div>';
    }
    
    $settings = get_option('support_ad_settings', array(
        'original_code' => '',
        'delay_seconds' => 5,
        'enable_anchor' => true,
        'enable_interstitial' => true
    ));
    ?>
    <div class="wrap">
        <h1>📢 수익화 광고 시스템 설정</h1>
        <form method="post" action="">
            <?php wp_nonce_field('support_save_ads'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="ad_code">메인 광고 코드</label></th>
                    <td>
                        <textarea id="ad_code" name="ad_code" rows="10" class="large-text code"><?php echo esc_textarea($settings['original_code']); ?></textarea>
                        <p class="description">애드센스, 타뮬라 등 코드를 넣으면 자동으로 앵커/전면/수동 코드로 분리됩니다.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">자동 송출 설정</th>
                    <td>
                        <label><input type="checkbox" name="enable_anchor" value="1" <?php checked($settings['enable_anchor']); ?>> 앵커 광고 (상단/하단 고정)</label><br>
                        <label><input type="checkbox" name="enable_interstitial" value="1" <?php checked($settings['enable_interstitial']); ?>> 전면 광고 (페이지 로드 시)</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">전면 광고 딜레이</th>
                    <td><input type="number" name="delay_seconds" value="<?php echo esc_attr($settings['delay_seconds']); ?>" class="small-text"> 초</td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="save_ads" class="button button-primary" value="설정 저장"></p>
        </form>
        <div class="card">
            <h3>📌 수동 광고 넣는 법</h3>
            <p>글 작성 시 원하는 위치에 <code>[manual_ad]</code> 라고 적으면 네이티브 광고가 나옵니다.</p>
        </div>
    </div>
    <?php
}

// ==================== 광고 코드 자동 개조 로직 ====================
function support_process_ad_code($ad_code) {
    $result = array('anchor' => $ad_code, 'interstitial' => $ad_code, 'manual' => $ad_code);
    
    // 애드센스 감지 시 최적화 코드로 변환
    if (strpos($ad_code, 'adsbygoogle') !== false && preg_match('/ca-pub-(\d+)/', $ad_code, $matches)) {
        $client_id = 'ca-pub-' . $matches[1];
        // 앵커 (상/하단 자동)
        $result['anchor'] = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='.$client_id.'" crossorigin="anonymous"></script><ins class="adsbygoogle" style="display:block" data-ad-client="'.$client_id.'" data-ad-slot="0000000000" data-ad-format="autorelaxed" data-full-width-responsive="true"></ins><script>(adsbygoogle = window.adsbygoogle || []).push({});</script>';
        // 전면
        $result['interstitial'] = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='.$client_id.'" crossorigin="anonymous"></script><ins class="adsbygoogle" style="display:block" data-ad-format="autorelaxed" data-ad-client="'.$client_id.'" data-ad-slot="0000000000"></ins><script>(adsbygoogle = window.adsbygoogle || []).push({});</script>';
    }
    return $result;
}

// ==================== 프론트엔드 광고 삽입 (자동) ====================
function support_auto_inject_ads() {
    $settings = get_option('support_ad_settings', array());
    
    // 앵커 광고
    if (!empty($settings['enable_anchor']) && !empty($settings['anchor_code'])) {
        echo '<div class="support-anchor-ad">' . $settings['anchor_code'] . '</div>';
    }
    
    // 전면 광고
    if (!empty($settings['enable_interstitial']) && !empty($settings['interstitial_code'])) {
        $delay = isset($settings['delay_seconds']) ? intval($settings['delay_seconds']) : 5;
        ?>
        <script>
        setTimeout(function() {
            var iDiv = document.createElement('div');
            iDiv.className = 'support-interstitial-ad';
            iDiv.innerHTML = '<div class="interstitial-inner"><button class="interstitial-close" onclick="this.parentElement.parentElement.remove()">닫기 X</button>' + <?php echo json_encode($settings['interstitial_code']); ?> + '</div>';
            document.body.appendChild(iDiv);
        }, <?php echo $delay * 1000; ?>);
        </script>
        <?php
    }
}
add_action('wp_footer', 'support_auto_inject_ads');

// ==================== 수동 광고 숏코드 [manual_ad] ====================
function support_manual_ad_shortcode() {
    $settings = get_option('support_ad_settings');
    if (!empty($settings['manual_code'])) {
        return '<div class="support-manual-ad">' . $settings['manual_code'] . '</div>';
    }
    return '';
}
add_shortcode('manual_ad', 'support_manual_ad_shortcode');

// ==================== 지원금 메타 카드 숏코드 [subsidy_card] ====================
function support_meta_card_shortcode($atts) {
    $a = shortcode_atts(array(
        'name' => '지원금 이름',
        'link' => '#'
    ), $atts);

    return sprintf('
    <div class="info-card featured support-meta-card">
        <div class="info-card-highlight">
            <span class="info-card-badge">기간한정 접수중</span>
            <div class="info-card-amount">%s</div>
            <div class="info-card-amount-sub">예산 소진 시 조기마감</div>
        </div>
        <div class="info-card-content">
            <div class="info-card-details">
                <div class="info-card-row">
                    <span class="info-card-label">지원대상</span>
                    <span class="info-card-value">대한민국 국민 누구나 (상세조건 확인)</span>
                </div>
                <div class="info-card-row">
                    <span class="info-card-label">지급금액</span>
                    <span class="info-card-value" style="color:#d32f2f">최대 지원금 확인하기</span>
                </div>
                <div class="info-card-row">
                    <span class="info-card-label">신청기간</span>
                    <span class="info-card-value">오늘 마감될 수 있습니다</span>
                </div>
            </div>
            <a href="%s" class="info-card-btn support-btn-link">
                %s 신청 바로가기 <span class="btn-arrow">→</span>
            </a>
        </div>
    </div>', 
    esc_html($a['name']), 
    esc_url($a['link']),
    esc_html($a['name'])
    );
}
add_shortcode('subsidy_card', 'support_meta_card_shortcode');

// ==================== 본문 자동 변환 필터 ====================
function support_content_filters($content) {
    if (is_admin()) return $content;

    // 1. 모든 링크(a태그)를 버튼 스타일로 변환 (이미지 제외)
    $content = preg_replace_callback(
        '/<a\s+(?!.*class=".*support-btn-link.*")([^>]*?)href=["\']([^"\']*)["\']([^>]*)>(.*?)<\/a>/is',
        function($matches) {
            // 이미지 포함 여부 확인
            if (strpos($matches[4], '<img') !== false) return $matches[0];
            // 숏코드 내부는 제외 (필요 시)
            return '<a href="' . $matches[2] . '" class="support-btn-link" ' . $matches[1] . $matches[3] . '>' . $matches[4] . ' <span class="btn-arrow">→</span></a>';
        },
        $content
    );

    // 2. 소제목(h2,h3)을 기준으로 카드 블록화
    // h2, h3 태그와 그 뒤에 오는 내용들을 div.support-card-block으로 감쌉니다.
    $content = preg_replace_callback(
        '/(<h[23][^>]*>.*?<\/h[23]>)(.*?)(?=(<h[23]|$))/is',
        function($matches) {
            $section = $matches[0];
            // 내용이 비어있지 않은 경우에만 카드로 변환
            if (trim(strip_tags($section))) {
                return '<div class="support-card-block">' . $section . '</div>';
            }
            return $section;
        },
        $content
    );

    return $content;
}
add_filter('the_content', 'support_content_filters', 20);
?>
