<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db_config.php';


if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header("Location:dashboard.php");
    exit();
}

$error = '';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    
    
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
    
    
    if (strlen($phone) !== 10) {
        $error = "رقم الهاتف غير صحيح. يجب أن يكون 10 أرقام.";
    } else {
        $phone = mysqli_real_escape_string($conn, $phone);
    }

    $year = mysqli_real_escape_string($conn, $_POST['academicYear']);
    $photo = 'https://res.cloudinary.com/dwymwx3ql/image/upload/v1684114559/profile_1_a0jqfx.png';
    $score = 0;
    $subscription = 'No';
    $allowed_devices = 5;
    $device = 1;

    
    $device_identifier = mysqli_real_escape_string($conn, $_POST['device_identifier']);

    
    $check_email = "SELECT * FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $check_email);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $error = "هذا البريد الإلكتروني موجود بالفعل. قم بالدخول إليه أو استخدم بريد إلكتروني آخر.";
    } else {
        
        $insert_query = "INSERT INTO users (name, email, password, number, year, photo, skore, subscription, allowed_devices, device) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "ssssssssii", $name, $email, $password, $phone, $year, $photo, $score, $subscription, $allowed_devices, $device);
        
        if (mysqli_stmt_execute($stmt)) {
            error_log("User inserted successfully: " . $email);

            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = mysqli_insert_id($conn);
            $_SESSION['user_name'] = $name;
            $_SESSION['year'] = $year;
            $_SESSION['email'] = $email;
            $_SESSION['number'] = $phone;

            
            $insert_examsusers_query = "INSERT INTO examsusers (email) VALUES (?)";
            $stmt_examsusers = mysqli_prepare($conn, $insert_examsusers_query);
            mysqli_stmt_bind_param($stmt_examsusers, "s", $email);
            mysqli_stmt_execute($stmt_examsusers);
            mysqli_stmt_close($stmt_examsusers);

            
            $insert_notifications_query = "INSERT INTO notificationss (email) VALUES (?)";
            $stmt_notifications = mysqli_prepare($conn, $insert_notifications_query);
            mysqli_stmt_bind_param($stmt_notifications, "s", $email);
            mysqli_stmt_execute($stmt_notifications);
            mysqli_stmt_close($stmt_notifications);
            
            $insert_degree_query = "INSERT INTO degree (email) VALUES (?)";
            $stmt_degree = mysqli_prepare($conn, $insert_degree_query);
            mysqli_stmt_bind_param($stmt_degree, "s", $email);
            mysqli_stmt_execute($stmt_degree);
            mysqli_stmt_close($stmt_degree);

            
            $browser = getBrowserName();
            $version = getBrowserVersion();
            $platform = getOS();
            $last_login = date('Y-m-d H:i:s');
            $active = 'Yes';

            
            $insert_device_query = "INSERT INTO user_devices (email, device_identifier, browser, version, platform, last_login, active) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_device_query);
            mysqli_stmt_bind_param($stmt, "sssssss", $email, $device_identifier, $browser, $version, $platform, $last_login, $active);

            if (mysqli_stmt_execute($stmt)) {
                $device_idd = mysqli_insert_id($conn);
                $_SESSION['device_idd'] = $device_idd;
                $_SESSION['device_active'] = $active;

                header("Location:dashboard.php");
                exit();
            } else {
                $error = "حدث خطأ أثناء تسجيل معلومات الجهاز. يرجى المحاولة مرة أخرى.";
            }
        } else {
            $error = "حدث خطأ أثناء إنشاء الحساب. يرجى المحاولة مرة أخرى.";
        }
    }
}


function getBrowserName() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) return 'Opera';
    elseif (strpos($user_agent, 'Edge')) return 'Edge';
    elseif (strpos($user_agent, 'Chrome')) return 'Chrome';
    elseif (strpos($user_agent, 'Safari')) return 'Safari';
    elseif (strpos($user_agent, 'Firefox')) return 'Firefox';
    elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) return 'Internet Explorer';
    return 'Unknown';
}

function getBrowserVersion() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $browser_name = getBrowserName();
    $known = array('Version', $browser_name, 'other');
    $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
    if (!preg_match_all($pattern, $user_agent, $matches)) return '';
    $i = count($matches['browser']);
    if ($i != 1) {
        $version = strripos($user_agent, "Version") < strripos($user_agent, $browser_name) ? $matches['version'][0] : $matches['version'][1];
    } else {
        $version = $matches['version'][0];
    }
    return $version;
}

function getOS() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $os_platform = "Unknown OS Platform";
    $os_array = array(
        '/windows nt 10/i'      =>  'Windows 10',
        '/windows nt 6.3/i'     =>  'Windows 8.1',
        '/windows nt 6.2/i'     =>  'Windows 8',
        '/windows nt 6.1/i'     =>  'Windows 7',
        '/windows nt 6.0/i'     =>  'Windows Vista',
        '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
        '/windows nt 5.1/i'     =>  'Windows XP',
        '/windows xp/i'         =>  'Windows XP',
        '/windows nt 5.0/i'     =>  'Windows 2000',
        '/windows me/i'         =>  'Windows ME',
        '/win98/i'              =>  'Windows 98',
        '/win95/i'              =>  'Windows 95',
        '/win16/i'              =>  'Windows 3.11',
        '/macintosh|mac os x/i' =>  'Mac OS X',
        '/mac_powerpc/i'        =>  'Mac OS 9',
        '/linux/i'              =>  'Linux',
        '/ubuntu/i'             =>  'Ubuntu',
        '/iphone/i'             =>  'iPhone',
        '/ipod/i'               =>  'iPod',
        '/ipad/i'               =>  'iPad',
        '/android/i'            =>  'Android',
        '/blackberry/i'         =>  'BlackBerry',
        '/webos/i'              =>  'Mobile'
    );
    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $os_platform = $value;
        }
    }
    return $os_platform;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل حساب جديد</title>
   
<link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">


<link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon-180.png">
<link rel="apple-touch-icon" sizes="167x167" href="/images/apple-touch-icon-167.png">


<link rel="manifest" href="/manifest.json">
<meta name="msapplication-TileImage" content="/images/icon-144.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    
    <style>
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box; 
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #89f7fe, #66a6ff);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
        }

        
        .register-form {
            background: rgba(255, 255, 255, 0.9);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        h1 {
            margin-bottom: 30px;
            color: #673AB7;
            font-size: 28px;
            font-weight: bold;
        }

        input, select, button {
            width: 100%;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 30px;
            border: none;
            font-size: 18px;
        }

        input {
            background-color: #f0f0f0;
            border: 2px solid #ececec;
            transition: border 0.3s ease;
        }

        input:focus {
            border: 2px solid #673AB7;
            outline: none;
        }

        button {
            background-color: #673AB7;
            color: white;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        button:hover {
            background-color: #512DA8;
            transform: translateY(-3px);
        }

.phone-input-container { position: relative; display: flex; align-items: center;}#phone { padding-left: 45px;  direction: ltr; text-align: left; width: 100%;}.phone-input-container::before { content: '20+'; position: absolute; left: 10px; top: 35%; transform: translateY(-50%); color: #000;  font-size: 16px; pointer-events: none;}#phone::placeholder { direction: rtl; text-align: right;} 

        
        .options a {
            color: #673AB7;
            text-decoration: none;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .options a:hover {
            color: #512DA8;
        }

      .error-message {
    color: red;
    font-size: 14px;
    margin-top: -10px;
    margin-bottom: 10px;
    text-align: right; 
    direction: rtl; 
    padding-right: 0; 
    margin-right: 0; 
}
        
        @media (max-width: 768px) {
            .register-form {
                padding: 20px;
                border-radius: 10px;
                margin: 0 10px; 
            }

            h1 {
                font-size: 24px;
            }

            input, select, button {
                font-size: 16px;
                padding: 12px;
            }

            .phone-input-container::before {
                left: 10px;
            }

            #phone {
                padding-left: 60px;
            }
        }

        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body {
            background: linear-gradient(-45deg, #89f7fe, #66a6ff, #fbc2eb, #a6c1ee);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }
        
    </style>
</head>
<body>

    <div class="register-form">
        <h1>تسجيل حساب جديد</h1>
        <?php
        if (isset($error)) {
            echo "<p style='color: red;'>$error</p>";
        }
        ?>
        <form id="registerForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="text" id="name" name="name" placeholder="اسم المستخدم" required autocomplete="off">
            <input type="email" id="email" name="email" placeholder="البريد الإلكتروني" required autocomplete="username">
            <input type="password" id="password" name="password" placeholder="كلمة المرور" required autocomplete="new-password">
            <div id="passwordError" class="error-message"></div>
           
<div class="phone-input-container">
<input type="tel" id="phone" name="phone" placeholder="رقم الهاتف" required autocomplete="off" maxlength="11">
</div>

<div id="phoneError" class="error-message"></div>
            <select id="academicYear" name="academicYear" required>
                <option value="">اختر الصف الدراسي</option>
                <option value="الصف الثالث الثانوي">الصف الثالث الثانوي</option>
                <option value="الصف الثاني الثانوي">الصف الثاني الثانوي</option>
                <option value="الصف الأول الثانوي">الصف الأول الثانوي</option>
            </select>
            <button type="submit">إنشاء الحساب</button>
        </form>
        <div class="options">
            <a href="login" id="loginLink" style="font-size: 18px; font-weight: bold; color: #673AB7; margin-top: 10px;">هل لديك حساب؟ تسجيل الدخول</a>
            <div style="height: 10px;"></div>
        </div>

        <div id="notification"></div>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const passwordInput = document.getElementById('password');
    const passwordError = document.getElementById('passwordError');
    const phoneInput = document.getElementById('phone');
    const phoneError = document.getElementById('phoneError');

    let passwordTouched = false;
    let phoneTouched = false;

    function showNotification(message, isError = false) {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.style.backgroundColor = isError ? '#FF5722' : '#4CAF50';
        notification.style.display = 'block';
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    }

    function getDeviceIdentifier() {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = 200;
    canvas.height = 200;

    ctx.font = '18px Arial';
    ctx.fillText('Canvas Fingerprint', 10, 50);
    ctx.beginPath();
    ctx.arc(100, 100, 50, 0, Math.PI * 2);
    ctx.stroke();

    const canvasFingerprint = canvas.toDataURL();
    const colorDepth = window.screen.colorDepth;
    const aspectRatio = (window.screen.width / window.screen.height).toFixed(2);

    const combinedInfo = canvasFingerprint + '|' + colorDepth + '|' + aspectRatio;
    return btoa(combinedInfo); 
}

document.querySelector('form').addEventListener('submit', function(e) {
    const deviceIdentifierInput = document.createElement('input');
    deviceIdentifierInput.type = 'hidden';
    deviceIdentifierInput.name = 'device_identifier';
    deviceIdentifierInput.value = getDeviceIdentifier();
    this.appendChild(deviceIdentifierInput);
});

    function validatePassword() {
        if (passwordInput.value.length < 8) {
            passwordError.textContent = 'يجب إدخال 8 حروف أو أرقام على الأقل لكلمة المرور';
            passwordError.style.display = 'block';
            return false;
        } else {
            passwordError.textContent = '';
            passwordError.style.display = 'none';
            return true;
        }
    }

    function validatePhone() {
        let value = phoneInput.value.replace(/\D/g, '');
        if (value.startsWith('20')) {
            value = value.slice(2);
        }

        if ((value.startsWith('0') && value.length !== 11) || (!value.startsWith('0') && value.length !== 10)) {
            phoneError.textContent = 'رقم الهاتف غير صحيح، قم بإدخاله بشكل صحيح.';
            phoneError.style.display = 'block';
            return false;
        } else {
            phoneError.textContent = '';
            phoneError.style.display = 'none';
            return true;
        }
    }

    form.addEventListener('submit', function(e) {
        passwordTouched = true;
        phoneTouched = true;
        let isValid = validatePassword() && validatePhone();

        if (!isValid) {
            e.preventDefault();
        } else {
            const deviceIdentifierInput = document.createElement('input');
            deviceIdentifierInput.type = 'hidden';
            deviceIdentifierInput.name = 'device_identifier';
            deviceIdentifierInput.value = getDeviceIdentifier();
            this.appendChild(deviceIdentifierInput);
        }
    });

    passwordInput.addEventListener('blur', function() {
        if (!passwordTouched) {
            passwordTouched = true;
            validatePassword();
        }
    });

    passwordInput.addEventListener('input', function() {
        if (passwordTouched) {
            validatePassword();
        }
    });

    phoneInput.addEventListener('blur', function() {
        if (!phoneTouched) {
            phoneTouched = true;
            validatePhone();
        }
    });

    phoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.startsWith('20')) {
            value = value.slice(2);
        }
        if (value.length > 11) {
            value = value.slice(0, 11);
        }
        e.target.value = value;

        if (phoneTouched) {
            validatePhone();
        }
    });

    history.pushState(null, null, location.href);
    window.onpopstate = function(event) {
        history.go(1);
    };

    setInterval(function() {
        fetch('check_session.php')
            .then(response => response.json())
            .then(data => {
                if (data.logged_in) {
                    window.location.href = 'dashboard.php';
                }
            });
    }, 5000);

    
    document.addEventListener('click', function(e) {
        if (e.target.tagName === 'A' && e.target.getAttribute('target') === '_blank') {
            e.preventDefault();
            window.location.href = e.target.href;
        }
    });
}); 
 

document.addEventListener('keydown', function(e) {
    const activeElement = document.activeElement;

    
    if (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA') {
      return; 
    }

    
    if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
      e.preventDefault();
    }

    
    if (e.ctrlKey && e.keyCode === 85) {
      e.preventDefault();
    }
});


document.addEventListener('contextmenu', function(e) {
   const activeElement = document.activeElement;

   
   if (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA') {
     return; 
   }

   e.preventDefault(); 
});
    </script>
</body>
</html>
