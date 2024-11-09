<?php
include 'header.php';
include '../netting/connectDb.php'; // Veritabanı bağlantısı

// Hata gösterimini aç
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Hata raporlamayı etkinleştir
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Veritabanında en küçük TC Kimlik Numarasını al
    $query = $pdo->prepare("SELECT MIN(CAST(customerTCKN AS UNSIGNED)) AS minTCKN FROM customers WHERE customerTCKN IS NOT NULL");
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);

    // En küçük TC Kimlik Numarasını 1 artır ve kontrol et
    if ($result && !empty($result['minTCKN'])) {
        do {
            $suggestedTCKN = str_pad($result['minTCKN'] + 1, 11, "0", STR_PAD_LEFT); // TC Kimlik Numarasının 11 haneli olmasını sağlıyoruz
            $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE customerTCKN = :suggestedTCKN");
            $checkQuery->bindParam(':suggestedTCKN', $suggestedTCKN);
            $checkQuery->execute();
            $exists = $checkQuery->fetchColumn();

            if ($exists > 0) {
                $result['minTCKN']++;
            } else {
                break;
            }
        } while (true);
    } else {
        $suggestedTCKN = "00000000001"; // Veritabanında TCKN yoksa varsayılan başlangıç numarası
    }

} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage();
}



// AJAX isteği geldiğinde şehir ve ilçe verilerini getirme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'fetchCities' && isset($_POST['countryId'])) {
        $countryId = $_POST['countryId'];
        $queryCity = $pdo->prepare("SELECT cityId, cityName FROM cities WHERE countryId = :countryId ORDER BY cityName ASC");
        $queryCity->bindParam(':countryId', $countryId, PDO::PARAM_INT);
        $queryCity->execute();
        $cities = $queryCity->fetchAll(PDO::FETCH_ASSOC);

        // Şehir listesine "Seçiniz" seçeneğini ekleyin
        echo '<option value="">Şehir Seçiniz</option>';
        foreach ($cities as $city) {
            echo '<option value="' . htmlspecialchars($city['cityId']) . '">' . htmlspecialchars($city['cityName']) . '</option>';
        }
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'fetchDistricts' && isset($_POST['cityId'])) {
        $cityId = $_POST['cityId'];
        $queryDistrict = $pdo->prepare("SELECT districtId, districtName FROM districts WHERE districtCityId = :cityId ORDER BY districtName ASC");
        $queryDistrict->bindParam(':cityId', $cityId, PDO::PARAM_INT);
        $queryDistrict->execute();
        $districts = $queryDistrict->fetchAll(PDO::FETCH_ASSOC);

        // İlçe listesine "Seçiniz" seçeneğini ekleyin
        echo '<option value="">İlçe Seçiniz</option>';
        foreach ($districts as $district) {
            echo '<option value="' . htmlspecialchars($district['districtId']) . '">' . htmlspecialchars($district['districtName']) . '</option>';
        }
        exit;
    }
}


// Ülke verilerini çekme (örneğin Türkiye varsayılan olarak seçili)
$queryCountry = $pdo->prepare("SELECT countryId, countryName FROM countries WHERE countryStatus = 1 ORDER BY countryId ASC");
$queryCountry->execute();
$countries = $queryCountry->fetchAll(PDO::FETCH_ASSOC);
?>










<!-- page content -->
<div class="right_col" role="main">
  <div class="">
    <div class="page-title"></div>
    <div class="clearfix"></div>
    <div class="row justify-content-md-center">
      <div class="col-md-6 col-sm-12 col-xs-12">
        <div class="x_panel">
          <div class="x_title">
            <div class="row">
              <!-- Başlık Alanı -->
              <div class="col-md-6">
                <h2>Yeni Müşteri Ekle <small>Yeni müşteri ekleme formu</small></h2>
              </div>
              <!-- Uyarı Mesajı Alanı -->
              <div class="col-md-6 text-right">
                <small>
                 <?php if (isset($_GET['status'])): ?>
                  <div id="statusMessage">
                    <?php if ($_GET['status'] == 'success'): ?>
                      <span style="color: white; background-color: green; padding: 5px; border-radius: 3px; font-weight: bold;">
                        İşlem başarıyla gerçekleştirildi...
                      </span>
                    <?php elseif ($_GET['status'] == 'tckn_error'): ?>
                      <span style="color: white; background-color: red; padding: 5px; border-radius: 3px; font-weight: bold;">
                        Aynı TC Kimlik Numarasına ait başka bir kayıt var!
                      </span>
                    <?php elseif ($_GET['status'] == 'email_error'): ?>
                      <span style="color: white; background-color: red; padding: 5px; border-radius: 3px; font-weight: bold;">
                        Aynı e-mail adresine sahip başka bir kullanıcı var!
                      </span>
                    <?php elseif ($_GET['status'] == 'error'): ?>
                      <span style="color: white; background-color: red; padding: 5px; border-radius: 3px; font-weight: bold;">
                        İşlem sırasında bir hata oluştu!
                      </span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                </small>
              </div>
            </div>
            <div class="clearfix"></div>
          </div>
          <div class="x_content">
            <br />
            <form id="customer-form" data-parsley-validate class="form-horizontal form-label-left" action="customerNewSave.php" method="POST">
              
             <!-- Müşteri Tipi -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerType">Müşteri Tipi <span class="required" style="color: red;">*</span></label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <select id="customerType" name="customerType" required="required" class="form-control col-md-7 col-xs-12">
                    <option value="bireysel">Bireysel</option>
                    <option value="kurumsal">Kurumsal</option>
                  </select>
                </div>
              </div>
              
             <!-- Müşteri Kodu Ön Ek -->
                <div class="form-group">
                  <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerPreCode">Müşteri Kodu Ön Ek <span class="required" style="color: red;">*</span></label>
                  <div class="col-md-9 col-sm-9 col-xs-12">
                    <input type="text" id="customerPreCode" name="customerPreCode" value="MANUEL" readonly="readonly" class="form-control col-md-7 col-xs-12">
                  </div>
                </div>

              <!-- Müşteri Adı -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerName">Müşteri Adı <span class="required" style="color: red;">*</span></label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <input type="text" id="customerName" name="customerName" required="required" class="form-control col-md-7 col-xs-12">
                </div>
              </div>
              
              <!-- Müşteri Soyadı -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerSurname">Müşteri Soyadı <span class="required" style="color: red;">*</span></label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <input type="text" id="customerSurname" name="customerSurname" required="required" class="form-control col-md-7 col-xs-12">
                </div>
              </div>

            <!-- TC Kimlik Numarası -->
            <div class="form-group">
              <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerTCKN">TC Kimlik Numarası <span class="required" style="color: red;">*</span></label>
              <div class="col-md-9 col-sm-9 col-xs-12">
                <input type="text" id="customerTCKN" name="customerTCKN" class="form-control col-md-7 col-xs-12" required="required" maxlength="11" pattern="\d{11}" value="<?= htmlspecialchars($suggestedTCKN) ?>">
              </div>
            </div>

              <!-- Firma Adı -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerCompanyName">Firma Adı</label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <input type="text" id="customerCompanyName" name="customerCompanyName" class="form-control col-md-7 col-xs-12">
                </div>
              </div>

              <!-- Vergi Dairesi -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerTaxOffice">Vergi Dairesi</label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <input type="text" id="customerTaxOffice" name="customerTaxOffice" class="form-control col-md-7 col-xs-12">
                </div>
              </div>

              <!-- Vergi Numarası -->
                <div class="form-group">
                  <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerTaxNumber">Vergi Numarası</label>
                  <div class="col-md-9 col-sm-9 col-xs-12">
                    <input type="number" id="customerTaxNumber" name="customerTaxNumber" class="form-control col-md-7 col-xs-12" step="1">
                  </div>
                </div>


              <!-- E-posta -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerEmail">E-posta</label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <input type="email" id="customerEmail" name="customerEmail" class="form-control col-md-7 col-xs-12">
                </div>
              </div>

              <!-- GSM -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerGsm">GSM (Cep Telefonu)</label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <div class="input-group">
                    <span class="input-group-addon">+90</span>
                    <input type="tel" id="customerGsm" name="customerGsm" placeholder="(___) ___ __ __" class="form-control col-md-7 col-xs-12" oninput="formatPhoneNumber(this)">
                  </div>
                </div>
              </div>

              <!-- Sabit Telefon -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerPhone">Sabit Telefon</label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <div class="input-group">
                    <span class="input-group-addon">+90</span>
                    <input type="tel" id="customerPhone" name="customerPhone" placeholder="(___) ___ __ __" class="form-control col-md-7 col-xs-12" oninput="formatPhoneNumber(this)">
                  </div>
                </div>
              </div>

              <!-- Doğum Tarihi -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerBirthday">Doğum Tarihi</label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <div class="row">
                    <div class="col-md-4 col-sm-4 col-xs-4">
                      <select id="birthDay" name="birthDay" class="form-control">
                        <option value="">Gün</option>
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                          <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                      </select>
                    </div>
                    <div class="col-md-4 col-sm-4 col-xs-4">
                      <select id="birthMonth" name="birthMonth" class="form-control">
                        <option value="">Ay</option>
                        <option value="1">Ocak</option>
                        <option value="2">Şubat</option>
                        <option value="3">Mart</option>
                        <option value="4">Nisan</option>
                        <option value="5">Mayıs</option>
                        <option value="6">Haziran</option>
                        <option value="7">Temmuz</option>
                        <option value="8">Ağustos</option>
                        <option value="9">Eylül</option>
                        <option value="10">Ekim</option>
                        <option value="11">Kasım</option>
                        <option value="12">Aralık</option>
                      </select>
                    </div>
                    <div class="col-md-4 col-sm-4 col-xs-4">
                      <select id="birthYear" name="birthYear" class="form-control">
                        <option value="">Yıl</option>
                        <?php for ($i = date("Y"); $i >= 1900; $i--): ?>
                          <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Cinsiyet -->
                <div class="form-group">
                  <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerGender">Cinsiyet <span class="required" style="color: red;">*</span></label>
                  <div class="col-md-9 col-sm-9 col-xs-12">
                    <div id="gender" class="radio-group">
                      <label class="radio-inline">
                        <input type="radio" name="customerGender" value="male" required> Erkek
                      </label>
                      <label class="radio-inline">
                        <input type="radio" name="customerGender" value="female" required> Kadın
                      </label>
                    </div>
                  </div>
                </div>


             <!-- Ülke -->
                <div class="form-group">
                  <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerCountry">Ülke</label>
                  <div class="col-md-9 col-sm-9 col-xs-12">
                    <select id="customerCountry" name="customerCountry" class="form-control col-md-7 col-xs-12" onchange="fetchCities(this.value)">
                      <option value="">Ülke Seçiniz</option> <!-- Varsayılan boş seçenek -->
                      <?php foreach ($countries as $country): ?>
                        <option value="<?= htmlspecialchars($country['countryId']) ?>"><?= htmlspecialchars($country['countryName']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>


              <!-- Şehir -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerCity">Şehir</label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <select id="customerCity" name="customerCity" class="form-control col-md-7 col-xs-12" onchange="fetchDistricts(this.value)">
                    <option value="">Şehir Seçiniz</option>
                    <?php foreach ($cities as $city): ?>
                      <option value="<?= htmlspecialchars($city['cityId']) ?>" <?= $city['cityId'] == 34 ? 'selected' : '' ?>><?= htmlspecialchars($city['cityName']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- İlçe -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerDistrict">İlçe</label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <select id="customerDistrict" name="customerDistrict" class="form-control col-md-7 col-xs-12">
                    <option value="">İlçe Seçiniz</option>
                  </select>
                </div>
              </div>

              <!-- Adres Satırı 1 -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="addressLine1">Adres Satırı 1</label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <input type="text" id="addressLine1" name="addressLine1" class="form-control col-md-7 col-xs-12">
                </div>
              </div>
              
              <!-- Adres Satırı 2 -->
              <div class="form-group">
                <label class="control-label col-md-3 col-sm-3 col-xs-12" for="addressLine2">Adres Satırı 2</label>
                <div class="col-md-9 col-sm-9 col-xs-12">
                  <input type="text" id="addressLine2" name="addressLine2" class="form-control col-md-7 col-xs-12">
                </div>
              </div>
              
              <!-- Posta Kodu -->
                <div class="form-group">
                  <label class="control-label col-md-3 col-sm-3 col-xs-12" for="customerPostalCode">Posta Kodu</label>
                  <div class="col-md-9 col-sm-9 col-xs-12">
                    <input type="number" id="customerPostalCode" name="customerPostalCode" class="form-control col-md-7 col-xs-12" step="1">
                  </div>
                </div>

              <div class="ln_solid"></div>
              <div class="form-group">
                <div class="col-md-9 col-sm-9 col-xs-12 col-md-offset-3">
                  <button type="submit" class="btn btn-success">Kaydet</button>
                  <button type="reset" class="btn btn-primary">Temizle</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    </br></br></br></br></br>
  </div>
</div>
<!-- /page content -->

<script>
// Ülke seçildiğinde şehirleri getirmek için AJAX fonksiyonu
function fetchCities(countryId) {
  if (countryId) {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function() {
      if (this.status === 200) {
        document.getElementById("customerCity").innerHTML = this.responseText;
        document.getElementById("customerDistrict").innerHTML = '<option value="">İlçe Seçiniz</option>';
      }
    };
    xhr.send("action=fetchCities&countryId=" + countryId);
  } else {
    document.getElementById("customerCity").innerHTML = '<option value="">Şehir Seçiniz</option>';
    document.getElementById("customerDistrict").innerHTML = '<option value="">İlçe Seçiniz</option>';
  }
}

// Şehir seçildiğinde ilçeleri getirmek için AJAX fonksiyonu
function fetchDistricts(cityId) {
  if (cityId) {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function() {
      if (this.status === 200) {
        document.getElementById("customerDistrict").innerHTML = this.responseText;
      }
    };
    xhr.send("action=fetchDistricts&cityId=" + cityId);
  } else {
    document.getElementById("customerDistrict").innerHTML = '<option value="">İlçe Seçiniz</option>';
  }
}


// Telefon numarası formatlamak için yardımcı fonksiyon
function formatPhoneNumber(input) {
  let phoneNumber = input.value.replace(/\D/g, ''); 
  if (phoneNumber.length > 10) phoneNumber = phoneNumber.slice(0, 10);

  let formattedNumber = '';
  if (phoneNumber.length > 0) formattedNumber += '(' + phoneNumber.substring(0, 3) + ')';
  if (phoneNumber.length >= 4) formattedNumber += ' ' + phoneNumber.substring(3, 6);
  if (phoneNumber.length >= 7) formattedNumber += ' ' + phoneNumber.substring(6, 8);
  if (phoneNumber.length >= 9) formattedNumber += ' ' + phoneNumber.substring(8, 10);

  input.value = formattedNumber;
}

// Başarı mesajı otomatik gizleme
window.addEventListener('DOMContentLoaded', (event) => {
    const statusMessage = document.getElementById('statusMessage');
    if (statusMessage) {
      setTimeout(() => {
        statusMessage.style.transition = 'opacity 0.5s ease';
        statusMessage.style.opacity = '0';
        setTimeout(() => statusMessage.style.display = 'none', 500); // Görsel olarak kaybolduktan sonra tamamen gizle
      }, 1000);
    }
  });

</script>



<?php
include 'footer.php';
?>
