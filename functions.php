<?php
/**
 * Functions.php - 지원금 스킨 관리 시스템
 * Description: 카드 관리 및 광고 관리 기능
 * Version: 1.0
 */

// ==================== 보안 설정 ====================
if (!defined('ABSPATH')) {
    exit;
}

// ==================== 테마 기본 설정 ====================
function subsidy_theme_setup() {
    // 로고 지원
    add_theme_support('custom-logo');
    
    // 제목 태그 지원
    add_theme_support('title-tag');
    
    // HTML5 지원
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
}
add_action('after_setup_theme', 'subsidy_theme_setup');

// CSS 및 JS 로드
function subsidy_enqueue_scripts() {
    wp_enqueue_style('subsidy-style', get_stylesheet_uri(), array(), '1.0');
    wp_enqueue_script('subsidy-custom', get_template_directory_uri() . '/custom.js', array(), '1.0', true);
    
    // AJAX 데이터 전달
    wp_localize_script('subsidy-custom', 'subsidyAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('subsidy_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'subsidy_enqueue_scripts');

// ==================== 관리자 메뉴 추가 ====================
function subsidy_admin_menu() {
    add_menu_page(
        '지원금 카드 관리',
        '지원금 관리',
        'manage_options',
        'subsidy-cards',
        'subsidy_cards_page',
        'dashicons-id-alt',
        30
    );
    
    add_submenu_page(
        'subsidy-cards',
        '광고 관리',
        '광고 관리',
        'manage_options',
        'subsidy-ads',
        'subsidy_ads_page'
    );
    
    add_submenu_page(
        'subsidy-cards',
        '설정',
        '설정',
        'manage_options',
        'subsidy-settings',
        'subsidy_settings_page'
    );
}
add_action('admin_menu', 'subsidy_admin_menu');

// ==================== 카드 관리 페이지 ====================
function subsidy_cards_page() {
    // 카드 추가 처리
    if (isset($_POST['add_card']) && check_admin_referer('subsidy_add_card')) {
        $keyword = sanitize_text_field($_POST['keyword']);
        
        if (!empty($keyword)) {
            $cards = get_option('subsidy_cards', array());
            
            // AI 자동 생성 (OpenAI API 없이 템플릿 기반)
            $generated = subsidy_generate_card_content($keyword);
            
            $cards[] = array(
                'id' => uniqid(),
                'keyword' => $keyword,
                'amount' => $generated['amount'],
                'amount_sub' => $generated['amount_sub'],
                'description' => $generated['description'],
                'target' => $generated['target'],
                'period' => $generated['period'],
                'featured' => false,
                'created' => current_time('mysql')
            );
            
            update_option('subsidy_cards', $cards);
            echo '<div class="notice notice-success"><p>카드가 추가되었습니다!</p></div>';
        }
    }
    
    // 카드 삭제 처리
    if (isset($_GET['delete']) && check_admin_referer('subsidy_delete_card_' . $_GET['delete'])) {
        $cards = get_option('subsidy_cards', array());
        $cards = array_filter($cards, function($card) {
            return $card['id'] !== $_GET['delete'];
        });
        update_option('subsidy_cards', array_values($cards));
        echo '<div class="notice notice-success"><p>카드가 삭제되었습니다!</p></div>';
    }
    
    // 인기 카드 설정
    if (isset($_GET['featured']) && check_admin_referer('subsidy_featured_card_' . $_GET['featured'])) {
        $cards = get_option('subsidy_cards', array());
        foreach ($cards as &$card) {
            $card['featured'] = ($card['id'] === $_GET['featured']);
        }
        update_option('subsidy_cards', $cards);
        echo '<div class="notice notice-success"><p>인기 카드가 설정되었습니다!</p></div>';
    }
    
    $cards = get_option('subsidy_cards', array());
    ?>
    <div class="wrap">
        <h1>지원금 카드 관리</h1>
        
        <div style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>새 카드 추가</h2>
            <form method="post">
                <?php wp_nonce_field('subsidy_add_card'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="keyword">지원금명 *</label></th>
                        <td>
                            <input type="text" name="keyword" id="keyword" class="regular-text" required 
                                   placeholder="예: 청년내일저축계좌">
                            <p class="description">지원금명만 입력하면 AI가 자동으로 내용을 생성합니다.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="add_card" class="button button-primary">
                        ✨ AI로 카드 생성하기
                    </button>
                </p>
            </form>
        </div>
        
        <h2>등록된 카드 (<?php echo count($cards); ?>개)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;">인기</th>
                    <th>지원금명</th>
                    <th>금액/혜택</th>
                    <th>설명</th>
                    <th>지원대상</th>
                    <th>신청시기</th>
                    <th>작업</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cards)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            등록된 카드가 없습니다. 위에서 새 카드를 추가하세요.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($cards as $card): ?>
                        <tr>
                            <td style="text-align: center;">
                                <?php if ($card['featured']): ?>
                                    <span style="font-size: 20px;">🔥</span>
                                <?php else: ?>
                                    <a href="?page=subsidy-cards&featured=<?php echo esc_attr($card['id']); ?>&_wpnonce=<?php echo wp_create_nonce('subsidy_featured_card_' . $card['id']); ?>" 
                                       title="인기 카드로 설정">⭐</a>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($card['keyword']); ?></strong></td>
                            <td><?php echo esc_html($card['amount']); ?></td>
                            <td><?php echo esc_html($card['description']); ?></td>
                            <td><?php echo esc_html($card['target']); ?></td>
                            <td><?php echo esc_html($card['period']); ?></td>
                            <td>
                                <a href="?page=subsidy-cards&delete=<?php echo esc_attr($card['id']); ?>&_wpnonce=<?php echo wp_create_nonce('subsidy_delete_card_' . $card['id']); ?>" 
                                   class="button button-small"
                                   onclick="return confirm('정말 삭제하시겠습니까?');">삭제</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-left: 4px solid #3182f6; border-radius: 4px;">
            <h3 style="margin-top: 0;">💡 사용 팁</h3>
            <ul style="margin: 10px 0;">
                <li>지원금명만 입력하면 AI가 CTR과 수익을 극대화하는 문구를 자동 생성합니다</li>
                <li>⭐를 클릭하여 인기 카드(🔥 표시)를 설정할 수 있습니다</li>
                <li>인기 카드는 첫 번째에 배치되며 더 눈에 띄는 디자인이 적용됩니다</li>
                <li>카드는 생성 순서대로 표시되며, 최대 9개까지 추천합니다</li>
            </ul>
        </div>
    </div>
    <?php
}

// ==================== AI 카드 내용 자동 생성 ====================
function subsidy_generate_card_content($keyword) {
    // 키워드 분석을 통한 스마트 템플릿 매칭 (API 없이)
    $keyword_lower = mb_strtolower($keyword);
    
    // 카테고리별 최적화된 템플릿
    $templates = array(
        // 청년 지원금
        'youth' => array(
            'keywords' => array('청년', '청소년', '대학생', '취업', '구직'),
            'amounts' => array('월 50만원', '최대 200만원', '연 600만원', '월 30만원', '최대 3000만원'),
            'amount_subs' => array('최대 3년 지원', '일시금 지급', '매월 지급', '조건 충족 시', '무이자 대출'),
            'descriptions' => array(
                '청년층의 경제적 자립을 돕는 맞춤형 지원 프로그램',
                '미래를 준비하는 청년들을 위한 특별 혜택',
                '청년 세대의 꿈을 응원하는 정부 지원금'
            ),
            'targets' => array('만 19~34세 청년', '만 18~39세 미만', '소득 하위 70%'),
            'periods' => array('상시 신청', '매년 1월~3월', '분기별 접수')
        ),
        
        // 주거 지원
        'housing' => array(
            'keywords' => array('주택', '전세', '월세', '임차', '주거'),
            'amounts' => array('최대 5000만원', '월 30만원', '최대 1억원', '보증금 80%'),
            'amount_subs' => array('연 1.5% 금리', '최대 10년', '무이자 2년', '최장 20년'),
            'descriptions' => array(
                '내 집 마련의 꿈을 현실로 만드는 주거 지원',
                '안정적인 주거 환경을 위한 특별 지원',
                '주거비 부담을 덜어주는 정부 지원금'
            ),
            'targets' => array('무주택 가구', '신혼부부', '소득 6000만원 이하'),
            'periods' => array('상시 신청', '매월 접수', '분기별 모집')
        ),
        
        // 창업 지원
        'startup' => array(
            'keywords' => array('창업', '사업', '소상공인', '자영업'),
            'amounts' => array('최대 1억원', '월 200만원', '5000만원', '최대 3억원'),
            'amount_subs' => array('무이자 3년', '컨설팅 지원', '사업화 지원', '최장 5년'),
            'descriptions' => array(
                '성공적인 창업을 위한 든든한 파트너',
                '예비 창업자의 꿈을 실현하는 지원금',
                '사업 성장을 가속화하는 맞춤 지원'
            ),
            'targets' => array('예비 창업자', '창업 3년 이내', '만 39세 이하'),
            'periods' => array('상시 모집', '연 2회 접수', '분기별 선발')
        ),
        
        // 교육 지원
        'education' => array(
            'keywords' => array('교육', '학자금', '등록금', '장학', '학비'),
            'amounts' => array('등록금 전액', '연 500만원', '최대 1000만원', '학기당 300만원'),
            'amount_subs' => array('성적 무관', '무이자 상환', '졸업 후 상환', '전액 지원'),
            'descriptions' => array(
                '교육의 기회를 넓히는 장학 지원',
                '학업에 전념할 수 있도록 돕는 혜택',
                '미래 인재 양성을 위한 특별 지원'
            ),
            'targets' => array('대학생', '고등학생', '소득 8분위 이하'),
            'periods' => array('학기별 신청', '매년 2월/8월', '상시 접수')
        ),
        
        // 일자리 지원
        'employment' => array(
            'keywords' => array('일자리', '채용', '고용', '근로', '취업지원'),
            'amounts' => array('월 80만원', '최대 960만원', '월 100만원', '연 1200만원'),
            'amount_subs' => array('최대 12개월', '기업 지원금', '취업 성공 시', '6개월 지급'),
            'descriptions' => array(
                '안정적인 일자리 창출을 위한 지원',
                '취업 성공을 돕는 맞춤형 프로그램',
                '근로자와 기업 모두 혜택받는 제도'
            ),
            'targets' => array('구직자', '신규 채용 기업', '청년 미취업자'),
            'periods' => array('상시 신청', '분기별 접수', '수시 모집')
        ),
        
        // 출산/육아 지원
        'childcare' => array(
            'keywords' => array('출산', '육아', '양육', '아이', '임신', '자녀'),
            'amounts' => array('첫째 200만원', '월 30만원', '둘째 300만원', '연 360만원'),
            'amount_subs' => array('일시금 지급', '매월 지원', '최대 3년', '조건 없음'),
            'descriptions' => array(
                '행복한 육아를 위한 든든한 지원',
                '아이 키우기 좋은 환경 조성',
                '출산 장려를 위한 특별 혜택'
            ),
            'targets' => array('출산 가구', '영유아 부모', '다자녀 가구'),
            'periods' => array('출산 시 신청', '상시 접수', '월별 지급')
        ),
        
        // 기본 템플릿
        'default' => array(
            'keywords' => array(),
            'amounts' => array('최대 300만원', '월 50만원', '최대 500만원', '연 600만원'),
            'amount_subs' => array('조건 충족 시', '신청자 전원', '선착순 마감', '심사 후 지급'),
            'descriptions' => array(
                '놓치면 안 되는 필수 지원금',
                '신청만 해도 받을 수 있는 혜택',
                '모르면 손해보는 정부 지원금'
            ),
            'targets' => array('대한민국 국민', '조건 충족자', '소득 기준 충족'),
            'periods' => array('상시 신청', '기간 내 접수', '매월 모집')
        )
    );
    
    // 키워드 매칭
    $matched_category = 'default';
    foreach ($templates as $category => $data) {
        foreach ($data['keywords'] as $k) {
            if (mb_strpos($keyword_lower, $k) !== false) {
                $matched_category = $category;
                break 2;
            }
        }
    }
    
    $template = $templates[$matched_category];
    
    // CTR 최적화를 위한 랜덤 선택 (다양성 확보)
    return array(
        'amount' => $template['amounts'][array_rand($template['amounts'])],
        'amount_sub' => $template['amount_subs'][array_rand($template['amount_subs'])],
        'description' => $template['descriptions'][array_rand($template['descriptions'])],
        'target' => $template['targets'][array_rand($template['targets'])],
        'period' => $template['periods'][array_rand($template['periods'])]
    );
}

// ==================== 광고 관리 페이지 ====================
function subsidy_ads_page() {
    if (isset($_POST['save_ads']) && check_admin_referer('subsidy_save_ads')) {
        // 앵커 광고 설정
        update_option('subsidy_anchor_ad_enabled', isset($_POST['anchor_ad_enabled']));
        update_option('subsidy_anchor_ad_code', wp_kses_post($_POST['anchor_ad_code']));
        
        // 전면 광고 설정
        update_option('subsidy_interstitial_enabled', isset($_POST['interstitial_enabled']));
        update_option('subsidy_interstitial_interval', intval($_POST['interstitial_interval']));
        update_option('subsidy_interstitial_code', wp_kses_post($_POST['interstitial_code']));
        
        // 수동 광고 설정
        update_option('subsidy_manual_ad_enabled', isset($_POST['manual_ad_enabled']));
        update_option('subsidy_manual_ad_code', wp_kses_post($_POST['manual_ad_code']));
        update_option('subsidy_manual_ad_position', sanitize_text_field($_POST['manual_ad_position']));
        
        echo '<div class="notice notice-success"><p>광고 설정이 저장되었습니다!</p></div>';
    }
    
    $anchor_enabled = get_option('subsidy_anchor_ad_enabled', false);
    $anchor_code = get_option('subsidy_anchor_ad_code', '');
    
    $interstitial_enabled = get_option('subsidy_interstitial_enabled', false);
    $interstitial_interval = get_option('subsidy_interstitial_interval', 5);
    $interstitial_code = get_option('subsidy_interstitial_code', '');
    
    $manual_enabled = get_option('subsidy_manual_ad_enabled', false);
    $manual_code = get_option('subsidy_manual_ad_code', '');
    $manual_position = get_option('subsidy_manual_ad_position', 'top');
    ?>
    <div class="wrap">
        <h1>광고 관리</h1>
        
        <form method="post">
            <?php wp_nonce_field('subsidy_save_ads'); ?>
            
            <!-- 앵커 광고 -->
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>⚓ 앵커 광고 (상단/하단 고정)</h2>
                <table class="form-table">
                    <tr>
                        <th>활성화</th>
                        <td>
                            <label>
                                <input type="checkbox" name="anchor_ad_enabled" value="1" <?php checked($anchor_enabled); ?>>
                                앵커 광고 사용
                            </label>
                            <p class="description">화면 상단 또는 하단에 고정되는 광고입니다. (애드센스 앵커 방식)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="anchor_ad_code">광고 코드</label></th>
                        <td>
                            <textarea name="anchor_ad_code" id="anchor_ad_code" rows="6" class="large-text code"><?php echo esc_textarea($anchor_code); ?></textarea>
                            <p class="description">애드센스, 타뮬라 등의 광고 코드를 입력하세요.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- 전면 광고 -->
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>📱 전면 광고 (페이지 전환 시)</h2>
                <table class="form-table">
                    <tr>
                        <th>활성화</th>
                        <td>
                            <label>
                                <input type="checkbox" name="interstitial_enabled" value="1" <?php checked($interstitial_enabled); ?>>
                                전면 광고 사용
                            </label>
                            <p class="description">설정한 시간 후 페이지 전환 시 전면 광고가 표시됩니다.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="interstitial_interval">표시 간격</label></th>
                        <td>
                            <select name="interstitial_interval" id="interstitial_interval">
                                <option value="1" <?php selected($interstitial_interval, 1); ?>>1분</option>
                                <option value="2" <?php selected($interstitial_interval, 2); ?>>2분</option>
                                <option value="5" <?php selected($interstitial_interval, 5); ?>>5분</option>
                                <option value="10" <?php selected($interstitial_interval, 10); ?>>10분</option>
                            </select>
                            <p class="description">마지막 전면 광고 표시 후 다음 광고까지의 시간</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="interstitial_code">광고 코드</label></th>
                        <td>
                            <textarea name="interstitial_code" id="interstitial_code" rows="6" class="large-text code"><?php echo esc_textarea($interstitial_code); ?></textarea>
                            <p class="description">애드센스 전면 광고 코드를 입력하세요.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- 수동 광고 -->
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>🎯 수동 광고 (콘텐츠 사이)</h2>
                <table class="form-table">
                    <tr>
                        <th>활성화</th>
                        <td>
                            <label>
                                <input type="checkbox" name="manual_ad_enabled" value="1" <?php checked($manual_enabled); ?>>
                                수동 광고 사용
                            </label>
                            <p class="description">카드 사이 또는 상단에 배치되는 광고입니다. (타뮬라 방식)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="manual_ad_position">광고 위치</label></th>
                        <td>
                            <select name="manual_ad_position" id="manual_ad_position">
                                <option value="top" <?php selected($manual_position, 'top'); ?>>상단 (인트로 아래)</option>
                                <option value="between" <?php selected($manual_position, 'between'); ?>>카드 사이 (자동 배치)</option>
                                <option value="both" <?php selected($manual_position, 'both'); ?>>상단 + 카드 사이</option>
                            </select>
                            <p class="description">CTR 최적화를 위한 광고 배치 위치</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="manual_ad_code">광고 코드</label></th>
                        <td>
                            <textarea name="manual_ad_code" id="manual_ad_code" rows="6" class="large-text code"><?php echo esc_textarea($manual_code); ?></textarea>
                            <p class="description">타뮬라, 데이블 등의 광고 코드를 입력하세요.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <button type="submit" name="save_ads" class="button button-primary button-large">
                    💾 설정 저장
                </button>
            </p>
        </form>
        
        <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
            <h3 style="margin-top: 0;">💡 광고 최적화 팁</h3>
            <ul style="margin: 10px 0;">
                <li><strong>앵커 광고:</strong> 스크롤 시에도 계속 보이므로 노출 극대화 (사용자 경험 고려)</li>
                <li><strong>전면 광고:</strong> 2~5분 간격 권장 (너무 짧으면 사용자 이탈)</li>
                <li><strong>수동 광고:</strong> 카드 사이 배치 시 자연스러운 흐름으로 CTR 극대화</li>
                <li><strong>조합 전략:</strong> 앵커 + 수동 조합이 수익성 가장 우수</li>
            </ul>
        </div>
    </div>
    <?php
}

// ==================== 설정 페이지 ====================
function subsidy_settings_page() {
    if (isset($_POST['save_settings']) && check_admin_referer('subsidy_save_settings')) {
        update_option('business_address', sanitize_text_field($_POST['business_address']));
        update_option('business_number', sanitize_text_field($_POST['business_number']));
        update_option('main_url', esc_url_raw($_POST['main_url']));
        
        echo '<div class="notice notice-success"><p>설정이 저장되었습니다!</p></div>';
    }
    
    $business_address = get_option('business_address', '');
    $business_number = get_option('business_number', '123-45-67890');
    $main_url = get_option('main_url', home_url());
    ?>
    <div class="wrap">
        <h1>설정</h1>
        
        <form method="post">
            <?php wp_nonce_field('subsidy_save_settings'); ?>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>기본 설정</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="business_address">사업자 주소</label></th>
                        <td>
                            <input type="text" name="business_address" id="business_address" 
                                   value="<?php echo esc_attr($business_address); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="business_number">사업자 번호</label></th>
                        <td>
                            <input type="text" name="business_number" id="business_number" 
                                   value="<?php echo esc_attr($business_number); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="main_url">메인 연결 URL</label></th>
                        <td>
                            <input type="url" name="main_url" id="main_url" 
                                   value="<?php echo esc_url($main_url); ?>" class="regular-text">
                            <p class="description">카드 클릭 시 이동할 기본 URL</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <button type="submit" name="save_settings" class="button button-primary button-large">
                    💾 설정 저장
                </button>
            </p>
        </form>
    </div>
    <?php
}

// ==================== 프론트엔드 카드 출력 ====================
function subsidy_get_cards_html() {
    $cards = get_option('subsidy_cards', array());
    $main_url = get_option('main_url', home_url());
    $manual_ad_enabled = get_option('subsidy_manual_ad_enabled', false);
    $manual_ad_code = get_option('subsidy_manual_ad_code', '');
    $manual_ad_position = get_option('subsidy_manual_ad_position', 'top');
    
    if (empty($cards)) {
        return '';
    }
    
    // 인기 카드를 맨 앞으로 정렬
    usort($cards, function($a, $b) {
        if ($a['featured'] && !$b['featured']) return -1;
        if (!$a['featured'] && $b['featured']) return 1;
        return 0;
    });
    
    $html = '';
    
    // 상단 광고 (manual_ad_position이 'top' 또는 'both'일 때)
    if ($manual_ad_enabled && ($manual_ad_position === 'top' || $manual_ad_position === 'both')) {
        $html .= '<div class="top-ad-section">' . $manual_ad_code . '</div>';
    }
    
    $html .= '<div class="info-card-grid">';
    
    foreach ($cards as $index => $card) {
        // 카드 사이 광고 배치 (0번, 3번, 6번 카드 앞)
        if ($manual_ad_enabled && ($manual_ad_position === 'between' || $manual_ad_position === 'both')) {
            if ($index === 0 || $index === 3 || $index === 6) {
                $html .= '<div class="ad-card"><div class="ad-content">' . $manual_ad_code . '</div></div>';
            }
        }
        
        $featured_class = $card['featured'] ? ' featured' : '';
        $badge = $card['featured'] ? '<span class="info-card-badge">🔥 인기</span>' : '';
        
        $html .= '<a class="info-card' . $featured_class . '" href="' . esc_url($main_url) . '">';
        $html .= '<div class="info-card-highlight">';
        if ($badge) $html .= $badge;
        $html .= '<div class="info-card-amount">' . esc_html($card['amount']) . '</div>';
        $html .= '<div class="info-card-amount-sub">' . esc_html($card['amount_sub']) . '</div>';
        $html .= '</div>';
        $html .= '<div class="info-card-content">';
        $html .= '<h3 class="info-card-title">' . esc_html($card['keyword']) . '</h3>';
        $html .= '<p class="info-card-desc">' . esc_html($card['description']) . '</p>';
        $html .= '<div class="info-card-details">';
        $html .= '<div class="info-card-row">';
        $html .= '<span class="info-card-label">지원대상</span>';
        $html .= '<span class="info-card-value">' . esc_html($card['target']) . '</span>';
        $html .= '</div>';
        $html .= '<div class="info-card-row">';
        $html .= '<span class="info-card-label">신청시기</span>';
        $html .= '<span class="info-card-value">' . esc_html($card['period']) . '</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="info-card-btn">지금 바로 신청하기 <span class="btn-arrow">→</span></div>';
        $html .= '</div>';
        $html .= '</a>';
    }
    
    $html .= '</div>';
    
    return $html;
}

// ==================== 광고 삽입 ====================
// 앵커 광고
function subsidy_inject_anchor_ad() {
    if (get_option('subsidy_anchor_ad_enabled', false)) {
        $code = get_option('subsidy_anchor_ad_code', '');
        if (!empty($code)) {
            echo '<div class="subsidy-anchor-ad" style="position: fixed; bottom: 0; left: 0; width: 100%; z-index: 9998; background: #fff; box-shadow: 0 -2px 10px rgba(0,0,0,0.1);">';
            echo $code;
            echo '</div>';
        }
    }
}
add_action('wp_footer', 'subsidy_inject_anchor_ad');

// 전면 광고 스크립트
function subsidy_interstitial_script() {
    if (get_option('subsidy_interstitial_enabled', false)) {
        $interval = get_option('subsidy_interstitial_interval', 5);
        $code = get_option('subsidy_interstitial_code', '');
        
        if (!empty($code)) {
            ?>
            <script>
            (function() {
                var interstitialInterval = <?php echo intval($interval); ?> * 60 * 1000; // 분을 밀리초로 변환
                var lastShownTime = sessionStorage.getItem('subsidy_last_interstitial');
                var interstitialCode = <?php echo json_encode($code); ?>;
                
                function showInterstitial() {
                    var now = new Date().getTime();
                    if (!lastShownTime || (now - parseInt(lastShownTime)) > interstitialInterval) {
                        // 전면 광고 표시
                        var overlay = document.createElement('div');
                        overlay.id = 'subsidy-interstitial';
                        overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 99999; display: flex; justify-content: center; align-items: center;';
                        
                        var container = document.createElement('div');
                        container.style.cssText = 'position: relative; max-width: 90%; max-height: 90%; background: #fff; border-radius: 12px; padding: 20px;';
                        
                        var closeBtn = document.createElement('button');
                        closeBtn.innerHTML = '✕';
                        closeBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; background: #333; color: #fff; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 18px; z-index: 1;';
                        closeBtn.onclick = function() {
                            document.body.removeChild(overlay);
                        };
                        
                        var adContent = document.createElement('div');
                        adContent.innerHTML = interstitialCode;
                        
                        container.appendChild(closeBtn);
                        container.appendChild(adContent);
                        overlay.appendChild(container);
                        document.body.appendChild(overlay);
                        
                        sessionStorage.setItem('subsidy_last_interstitial', now.toString());
                    }
                }
                
                // 페이지 전환 감지 (링크 클릭)
                document.addEventListener('click', function(e) {
                    var link = e.target.closest('a');
                    if (link && link.href && link.href.indexOf(window.location.hostname) !== -1) {
                        setTimeout(showInterstitial, 100);
                    }
                });
            })();
            </script>
            <?php
        }
    }
}
add_action('wp_footer', 'subsidy_interstitial_script');

// ==================== 숏코드 ====================
// [subsidy_cards] 숏코드로 카드 출력
function subsidy_cards_shortcode() {
    return subsidy_get_cards_html();
}
add_shortcode('subsidy_cards', 'subsidy_cards_shortcode');

// ==================== 관리자 스타일 ====================
function subsidy_admin_styles() {
    ?>
    <style>
        .subsidy-admin-notice {
            padding: 15px;
            background: #f0f9ff;
            border-left: 4px solid #3182f6;
            margin: 20px 0;
        }
        .subsidy-admin-notice h3 {
            margin-top: 0;
            color: #3182f6;
        }
        .wp-list-table .column-featured {
            width: 50px;
            text-align: center;
        }
    </style>
    <?php
}
add_action('admin_head', 'subsidy_admin_styles');

// ==================== 데이터베이스 초기화 ====================
function subsidy_install() {
    // 기본 옵션 설정
    if (!get_option('subsidy_cards')) {
        update_option('subsidy_cards', array());
    }
    
    // 기본 광고 설정
    add_option('subsidy_anchor_ad_enabled', false);
    add_option('subsidy_interstitial_enabled', false);
    add_option('subsidy_manual_ad_enabled', false);
    add_option('subsidy_interstitial_interval', 5);
}
register_activation_hook(__FILE__, 'subsidy_install');

// ==================== 관리자 알림 ====================
function subsidy_admin_notices() {
    $cards = get_option('subsidy_cards', array());
    
    if (empty($cards) && isset($_GET['page']) && $_GET['page'] === 'subsidy-cards') {
        ?>
        <div class="notice notice-info is-dismissible">
            <p><strong>🎉 지원금 카드 관리 시작하기!</strong></p>
            <p>지원금명만 입력하면 AI가 자동으로 CTR과 수익을 최적화한 카드를 생성합니다.</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'subsidy_admin_notices');
