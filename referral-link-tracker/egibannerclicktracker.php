<?php
/*
Plugin Name: Referral Link Tracker
Description: Reklam banner tıklamalarını, tıklandığı sayfayı kaydeder ve yönetim panelinde raporlar. (V3.0: Anti-Bot ve Tekil Tıklama Takibi eklendi.)
Version: 3.0
Author: Sayid Özcan
*/

// Güvenlik: Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Global olarak tablo isimlerini tanımla
global $egit_btc_db_version;
$egit_btc_db_version = '3.0';

// ----------------------------------------------------
// 1. AKTİVASYON: Veritabanı Tabloları (Yeni Alanlar Eklendi)
// ----------------------------------------------------
function egit_btc_activate() {
    global $wpdb;
    global $egit_btc_db_version;

    // Tablo 1: Tıklama Kayıtları
    $table_clicks = $wpdb->prefix . 'banner_clicks';
    // Tablo 2: Banner Link Tanımları
    $table_links  = $wpdb->prefix . 'banner_links';

    $charset_collate = $wpdb->get_charset_collate();

    // SQL 1: Tıklama Kayıtları Tablosu (is_unique ve is_bot alanları eklendi)
    $sql_clicks = "CREATE TABLE $table_clicks (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        banner_id varchar(100) NOT NULL,
        target_url text NOT NULL,
        click_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        ip_address varchar(45) NOT NULL,
        user_agent text NOT NULL,
        referrer_url text, 
        is_unique tinyint(1) DEFAULT 0 NOT NULL, 
        is_bot tinyint(1) DEFAULT 0 NOT NULL, 
        PRIMARY KEY (id),
        KEY banner_id (banner_id),
        KEY ip_address (ip_address)
    ) $charset_collate;";

    // SQL 2: Banner Link Tanımları Tablosu (Değişmedi)
    $sql_links = "CREATE TABLE $table_links (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        banner_id varchar(100) NOT NULL, 
        link_name varchar(255) NOT NULL,
        target_url text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        KEY banner_id (banner_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_clicks );
    dbDelta( $sql_links );

    update_option( 'egit_btc_db_version', $egit_btc_db_version );
}
register_activation_hook( __FILE__, 'egit_btc_activate' );

// ----------------------------------------------------
// 2. TIKLAMA TAKİBİ VE YÖNLENDİRME (Engelleme Mantığı Düzeltildi)
// ----------------------------------------------------
function egit_btc_handle_click_tracking() {
    if ( isset( $_GET['egit_track'] ) ) {
        global $wpdb;
        $table_clicks = $wpdb->prefix . 'banner_clicks';
        $table_links  = $wpdb->prefix . 'banner_links';

        $banner_id = sanitize_text_field( $_GET['egit_track'] );
        $link_data = $wpdb->get_row( $wpdb->prepare(
            "SELECT target_url FROM $table_links WHERE banner_id = %s",
            $banner_id
        ) );

        if ( ! $link_data || empty( $link_data->target_url ) ) {
            return;
        }

        $target_url   = $link_data->target_url;
        $ip_address   = $_SERVER['REMOTE_ADDR'];
        $user_agent   = $_SERVER['HTTP_USER_AGENT'];
        $referrer_url = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( $_SERVER['HTTP_REFERER'] ) : 'Referans Bilgisi Yok / Doğrudan Erişim';

        // --- BOT VE TEKİL TIKLAMA KONTROL MANTIĞI ---

        $is_unique = 1; // Varsayılan olarak tekil sayılır
        $is_bot    = 0; // Varsayılan olarak bot değildir

        // 1. Son 24 saatteki tüm tıklamaları kontrol et
        $last_24h = date('Y-m-d H:i:s', strtotime('-24 hours'));

        $recent_clicks = $wpdb->get_results( $wpdb->prepare(
            "SELECT click_time, is_bot FROM $table_clicks WHERE ip_address = %s AND click_time >= %s ORDER BY click_time DESC",
            $ip_address, $last_24h
        ) );

        if ( ! empty( $recent_clicks ) ) {
            // Tekrarlayan Tıklama: Son 24 saatte tıklamışsa tekil değil
            $is_unique = 0;
        }

        // Bot Kontrolü: Aynı IP'den son 1 dakika içinde 3'ten fazla tıklama varsa
        $clicks_in_last_minute = 0;
        $last_minute = strtotime('-1 minute');

        foreach ($recent_clicks as $click) {
            if ( strtotime($click->click_time) > $last_minute ) {
                $clicks_in_last_minute++;
            }
        }

        // DÜZELTME: SADECE HIZLI TIKLAMA OLURSA BOT OLARAK İŞARETLE VE YÖNLENDİRMEYİ ENGELLE
        if ( $clicks_in_last_minute >= 3 ) {
            $is_bot = 1;
            $target_url = ''; // YÖNLENDİRMEYİ ENGELLE
            // Not: 1 dakika dolduktan sonra bu koşul sağlanmayacak, $is_bot 0 kalacak ve link tekrar çalışacaktır.
        }

        // 2. Veritabanına kaydetme
        $wpdb->insert(
            $table_clicks,
            array(
                'banner_id'    => $banner_id,
                'target_url'   => $target_url,
                'ip_address'   => $ip_address,
                'user_agent'   => $user_agent,
                'referrer_url' => $referrer_url,
                'is_unique'    => $is_unique,
                'is_bot'       => $is_bot
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
        );

        // 3. Kullanıcıyı asıl hedefine yönlendir (BOT değilse) veya 404 döndür
        if ( ! empty( $target_url ) ) {
            wp_redirect( $target_url, 302 );
            exit;
        } else {
            // YENİ BOT KONTROLÜ: Eğer $target_url boşaltıldıysa (Bot ise),
            // hemen 404 başlığı gönder ve scripti sonlandır.
            if ($is_bot == 1) {
                header("HTTP/1.1 404 Not Found");
                exit;
            }
        }
    }
}
add_action( 'init', 'egit_btc_handle_click_tracking' );
// ... (Diğer fonksiyonlar aynı kalır)

// --- YÖNETİM PANELİ KODLARI (Admin Menu, Form, Pages) ---
// (Bu fonksiyonlar, menü ve form yapısını korumak için burada kısaltılmıştır)

// 3. YÖNETİM PANELİ MENÜSÜNÜ EKLEME
function egit_btc_add_admin_menu() {
    add_menu_page( 'Referral Link Tracker', 'Link Takipçisi', 'manage_options', 'egit-click-tracker', 'egit_btc_links_page', 'dashicons-chart-bar', 6 );
    add_submenu_page( 'egit-click-tracker', 'Yeni Link Ekle', 'Yeni Link Ekle', 'manage_options', 'egit-click-tracker-add', 'egit_btc_add_link_page' );
    add_submenu_page( null, 'Tıklama Detayları', 'Tıklama Detayları', 'manage_options', 'egit-click-tracker-details', 'egit_btc_details_page' );
}
add_action( 'admin_menu', 'egit_btc_add_admin_menu' );

// 4. YENİ LİNK EKLEME SAYFASI VE FORM İŞLEMLERİ (Önceki Kodun Aynısı)
function egit_btc_add_link_page() {
    global $wpdb; $table_links = $wpdb->prefix . 'banner_links'; $message = '';
    // ... (Form işleme mantığı buraya gelir)
    if ( isset( $_POST['egit_btc_submit'] ) && check_admin_referer( 'egit_btc_add_link_nonce' ) ) {
        // ... (Veri temizleme ve ekleme kısmı önceki koddan)
        $link_name  = sanitize_text_field( $_POST['link_name'] );
        $target_url = esc_url_raw( $_POST['target_url'] );
        $banner_id  = sanitize_title( $link_name ) . '-' . time();
        if ( empty( $link_name ) || empty( $target_url ) ) { $message = '<div class="notice notice-error is-dismissible"><p>Lütfen tüm alanları doldurun.</p></div>'; } else {
            $insert_result = $wpdb->insert( $table_links, array('banner_id' => $banner_id, 'link_name' => $link_name, 'target_url' => $target_url), array('%s', '%s', '%s'));
            if ( $insert_result ) { $message = '<div class="notice notice-success is-dismissible"><p>Link başarıyla eklendi. Takip ID: <strong>' . $banner_id . '</strong></p></div>'; } else { $message = '<div class="notice notice-error is-dismissible"><p>Veritabanı hatası: Link eklenemedi.</p></div>'; }
        }
    }
    // ... (Form HTML kısmı buraya gelir)
    ?>
    <div class="wrap">
        <h1>Yeni Referral Linki Ekle</h1>
        <?php echo $message; ?>
        <form method="post">
            <?php wp_nonce_field( 'egit_btc_add_link_nonce' ); ?>
            <table class="form-table">
                <tbody>
                <tr><th scope="row"><label for="link_name">Link Adı / Tanım</label></th><td><input name="link_name" type="text" id="link_name" class="regular-text" required value="<?php echo isset($_POST['link_name']) ? esc_attr($_POST['link_name']) : ''; ?>"><p class="description">Bu linki yönetim panelinde tanımak için bir isim.</p></td></tr>
                <tr><th scope="row"><label for="target_url">Hedef URL</label></th><td><input name="target_url" type="url" id="target_url" class="large-text" required value="<?php echo isset($_POST['target_url']) ? esc_attr($_POST['target_url']) : ''; ?>"><p class="description">Kullanıcının tıklandıktan sonra yönlendirileceği asıl adres.</p></td></tr>
                </tbody>
            </table>
            <p class="submit"><input type="submit" name="egit_btc_submit" id="submit" class="button button-primary" value="Link Ekle"></p>
        </form>
    </div>
    <?php
}

// 5. TÜM LİNKLERİ LİSTELEME SAYFASI (Metrikler Güncellendi)
function egit_btc_links_page() {
    global $wpdb;
    $table_links = $wpdb->prefix . 'banner_links';
    $table_clicks = $wpdb->prefix . 'banner_clicks';
    $per_page = 20;

    // ... (Sayfalama ve Link Çekme Mantığı)
    $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $offset = ( $current_page - 1 ) * $per_page;
    $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_links" );
    $total_pages = ceil( $total_items / $per_page );
    $links = $wpdb->get_results( "SELECT id, link_name, banner_id, target_url, created_at FROM $table_links ORDER BY created_at DESC LIMIT $offset, $per_page" );
    $base_url = home_url( '/' );

    ?>
    <div class="wrap">
        <h1>Referral Link Listesi</h1>

        <a href="<?php echo admin_url( 'admin.php?page=egit-click-tracker-add' ); ?>" class="page-title-action">Yeni Link Ekle</a>

        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th scope="col" class="manage-column">Link Adı (Tanım)</th>
                <th scope="col" class="manage-column">Takip URL'si</th>
                <th scope="col" class="manage-column">Toplam Tıklama</th>
                <th scope="col" class="manage-column">Tekil Tıklama (24s)</th>
                <th scope="col" class="manage-column">Bot Tıklaması</th>
                <th scope="col" class="manage-column">İşlemler</th>
            </tr>
            </thead>
            <tbody>
            <?php if ( $links ): ?>
                <?php foreach ( $links as $link ):
                    $tracking_url = esc_url( add_query_arg( 'egit_track', $link->banner_id, $base_url ) );

                    // Metrikleri çek
                    $total_clicks = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_clicks WHERE banner_id = %s", $link->banner_id ) );
                    $unique_clicks = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_clicks WHERE banner_id = %s AND is_unique = 1", $link->banner_id ) );
                    $bot_clicks = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_clicks WHERE banner_id = %s AND is_bot = 1", $link->banner_id ) );

                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $link->link_name ); ?></strong><br><small>Hedef: <?php echo esc_html( $link->target_url ); ?></small></td>
                        <td><input type="text" onfocus="this.select();" readonly value="<?php echo $tracking_url; ?>" style="width:100%; max-width: 300px;"></td>
                        <td><?php echo number_format_i18n( $total_clicks ); ?></td>
                        <td style="color: green; font-weight: bold;"><?php echo number_format_i18n( $unique_clicks ); ?></td>
                        <td style="color: red;"><?php echo number_format_i18n( $bot_clicks ); ?></td>
                        <td>
                            <a href="<?php echo admin_url( 'admin.php?page=egit-click-tracker-details&id=' . $link->banner_id ); ?>">Detaylı Analiz</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6">Henüz eklenmiş bir link bulunmamaktadır.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <?php
        // Sayfalama kodları
        $pagination_args = array(
            'base'      => add_query_arg( 'paged', '%#%' ), 'format' => '', 'total' => $total_pages, 'current' => $current_page, 'prev_text' => '&laquo; Önceki', 'next_text' => 'Sonraki &raquo;', 'type' => 'plain',
        );
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links( $pagination_args );
        echo '</div></div>';
        ?>

    </div>
    <?php
}

// 6. TIKLAMA DETAYLARI / ANALİZ SAYFASI (Sütunlar Güncellendi)
function egit_btc_details_page() {
    if ( ! isset( $_GET['id'] ) ) { wp_die( 'Banner ID eksik.' ); }

    global $wpdb;
    $table_clicks = $wpdb->prefix . 'banner_clicks';
    $table_links  = $wpdb->prefix . 'banner_links';
    $banner_id    = sanitize_text_field( $_GET['id'] );
    $per_page     = 50;

    // Link bilgilerini çek
    $link_info = $wpdb->get_row( $wpdb->prepare( "SELECT link_name, target_url FROM $table_links WHERE banner_id = %s", $banner_id ) );
    if ( ! $link_info ) { wp_die( 'Banner bulunamadı.' ); }

    // Yeni metrikleri çek
    $total_clicks = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_clicks WHERE banner_id = %s", $banner_id ) );
    $unique_clicks = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_clicks WHERE banner_id = %s AND is_unique = 1", $banner_id ) );
    $bot_clicks = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_clicks WHERE banner_id = %s AND is_bot = 1", $banner_id ) );

    // Sayfalama
    $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $offset = ( $current_page - 1 ) * $per_page;
    $total_pages = ceil( $total_clicks / $per_page );

    // Tıklama kayıtlarını çek
    $clicks = $wpdb->get_results( $wpdb->prepare(
        "SELECT click_time, ip_address, referrer_url, user_agent, is_unique, is_bot FROM $table_clicks WHERE banner_id = %s ORDER BY click_time DESC LIMIT %d, %d",
        $banner_id, $offset, $per_page
    ) );

    ?>
    <div class="wrap">
        <h1>Tıklama Detayları: <?php echo esc_html( $link_info->link_name ); ?></h1>

        <p><a href="<?php echo admin_url( 'admin.php?page=egit-click-tracker' ); ?>">&laquo; Tüm Linklere Geri Dön</a></p>

        <div class="card">
            <h2>Özet Metrikler</h2>
            <ul>
                <li><strong>Toplam Ham Tıklama:</strong> <?php echo number_format_i18n( $total_clicks ); ?></li>
                <li><strong>✅ Tekil Tıklama (24 Saat):</strong> <span style="font-size: 1.2em; font-weight: bold; color: green;"><?php echo number_format_i18n( $unique_clicks ); ?></span></li>
                <li><strong>❌ Bot/Tekrarlayan Tıklama:</strong> <span style="font-size: 1.2em; font-weight: bold; color: red;"><?php echo number_format_i18n( $bot_clicks ); ?></span></li>
                <li><strong>Hedef URL:</strong> <a href="<?php echo esc_url( $link_info->target_url ); ?>" target="_blank"><?php echo esc_url( $link_info->target_url ); ?></a></li>
            </ul>
        </div>

        <h2>Tüm Tıklama Kayıtları (En Yeniden En Eskiye)</h2>

        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th scope="col" class="manage-column">Zaman</th>
                <th scope="col" class="manage-column">Durum</th>
                <th scope="col" class="manage-column">Tıklanan Sayfa (Referans)</th>
                <th scope="col" class="manage-column">IP Adresi</th>
                <th scope="col" class="manage-column">Tarayıcı/Cihaz (User Agent)</th>
            </tr>
            </thead>
            <tbody>
            <?php if ( $clicks ): ?>
                <?php foreach ( $clicks as $click ):
                    $status_label = ($click->is_bot) ? '<span style="color:red; font-weight:bold;">BOT TIKLAMA</span>' : (($click->is_unique) ? '<span style="color:green;">TEKİL</span>' : '<span style="color:orange;">TEKRAR</span>');
                    ?>
                    <tr>
                        <td><?php echo date_i18n( 'd F Y H:i:s', strtotime( $click->click_time ) ); ?></td>
                        <td><?php echo $status_label; ?></td>
                        <td><a href="<?php echo esc_url( $click->referrer_url ); ?>" target="_blank"><?php echo esc_html( $click->referrer_url ); ?></a></td>
                        <td><?php echo esc_html( $click->ip_address ); ?></td>
                        <td><abbr title="<?php echo esc_attr( $click->user_agent ); ?>"><?php echo esc_html( substr( $click->user_agent, 0, 70 ) ); ?>...</abbr></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">Bu link için henüz bir tıklama kaydı bulunmamaktadır.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <?php
        // Sayfalama bağlantıları
        $pagination_args = array(
            'base'      => add_query_arg( array( 'paged' => '%#%', 'id' => $banner_id ) ), 'format' => '', 'total' => $total_pages, 'current' => $current_page, 'prev_text' => '&laquo; Önceki', 'next_text' => 'Sonraki &raquo;', 'type' => 'plain',
        );
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links( $pagination_args );
        echo '</div></div>';
        ?>
    </div>
    <?php
}