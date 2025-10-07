# Referral Link Tracker (WordPress Eklentisi)

**Geliştirici:** Sayid Özcan

**Versiyon:** 3.1

Bu özel WordPress eklentisi, banner reklamlarınıza yapılan tıklamaları detaylı bir şekilde takip etmek, referans (tıklanan) sayfayı kaydetmek ve hızlı tıklama yapan botları engelleyerek raporlama güvenilirliğini artırmak için tasarlanmıştır.

## Özellikler

* **Detaylı Tıklama Takibi:** Tıklama zamanı, IP adresi, tarayıcı/cihaz bilgisi ve asıl yönlendirilen URL kaydı.
* **Referans (Referrer) Takibi:** Tıklamanın web sitenizdeki hangi sayfadan (makale, kategori, ana sayfa vb.) geldiğini kaydeder.
* **Anti-Bot Koruması:** Bir IP'den **1 dakika içinde 3 veya daha fazla** tıklama gelirse, tıklamayı **bot** olarak işaretler ve kullanıcının reklamverenin sitesine yönlendirilmesini engelleyerek sunucunuzu ve reklamvereni korur. (Botlar 404 hatası alır.)
* **Tekil Tıklama Metriği:** Son 24 saat içindeki ilk tıklamayı "Tekil" olarak işaretleyerek daha doğru raporlama sağlar.
* **Yönetim Arayüzü:** Yönetim panelinde link ekleme, link listesi ve her link için detaylı analiz (Tekil/Bot/Tekrar) sayfaları sunar.

## Kurulum

### Yöntem 1: ZIP Dosyası ile Yükleme

1.  `egibannerclicktracker` klasörünü ZIP formatında sıkıştırın (`egibannerclicktracker.zip`).
2.  WordPress Yönetim Paneli'ne gidin.
3.  **Eklentiler > Yeni Ekle > Eklenti Yükle** yolunu izleyin.
4.  Oluşturduğunuz `egibannerclicktracker.zip` dosyasını seçip yükleyin ve ardından **Etkinleştirin**.

### Yöntem 2: GitHub (Manuel)

1.  Tüm `egibannerclicktracker` klasörünü indirin.
2.  FTP veya cPanel dosya yöneticisi kullanarak bu klasörü `wp-content/plugins/` dizininin içine yükleyin.
3.  WordPress Yönetim Paneli'nden eklentiyi **Etkinleştirin**.

---

## Kullanım Kılavuzu

### A. Link Tanımlama

1.  WordPress Admin menüsünde **Link Takipçisi > Yeni Link Ekle** sayfasına gidin.
2.  Reklamınız için bir **Link Adı** (Örn: `Anasayfa_Ust_Banner`) ve **Hedef URL** (Kullanıcının gideceği nihai adres) girin.
3.  Link eklendiğinde sistem size otomatik bir **Banner ID** ve benzersiz bir **Takip URL'si** oluşturacaktır.

### B. Banner'a Link Ekleme

Oluşturulan **Takip URL'sini** kopyalayın. Reklam banner'ınızın veya metin bağlantınızın `href` niteliği olarak bu URL'yi kullanın.

**Örnek Takip URL'si:**
`https://sitenizinadi.com/?egit_track=anasayfa-ust-banner-1759823573`

### C. Analiz ve Raporlama

1.  **Link Takipçisi** ana sayfasına gidin.
2.  Tüm linklerinizi, **Toplam**, **Tekil** ve **Bot Tıklamaları** metrikleriyle birlikte göreceksiniz.
3.  Detaylı ham verilere ulaşmak için linkin yanındaki **Detaylı Analiz** butonuna tıklayın.
    * **Tekil (Unique):** Son 24 saatteki ilk tıklama.
    * **Tekrar (Tekrarlayan):** Son 24 saatteki ikinci ve sonraki tıklamalar.
    * **Bot/Engellendi:** 1 dakikada 3'ten fazla tıklama teşebbüsü.

---

## Geliştirici Notları (v3.1)

Bu eklenti, hız ve güvenlik düşünülerek tasarlanmıştır.

* **Anti-Bot Düzeltmesi:** Önceki versiyonlarda görülen, bot olarak işaretlenen bir IP'nin 24 saat boyunca engellenmesi sorunu giderildi. Artık engelleme, yalnızca hızlı tıklama periyodu (1 dakika) boyunca geçerlidir.
* **Veritabanı Tabloları:** Eklenti aktive edildiğinde aşağıdaki iki tablo oluşturulur (ön ek `wp_` yerine sitenizin ön eki kullanılır):
    * `[prefix]_banner_links`: Link tanımlarını (ID, Ad, Hedef URL) saklar.
    * `[prefix]_banner_clicks`: Her tıklama kaydını (Zaman, IP, Referans, Bot Durumu) saklar.
