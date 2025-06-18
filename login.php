<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_config.php';
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    
    if (isset($_SESSION['redirect_url'])) {
        $redirect_url = $_SESSION['redirect_url'];
        unset($_SESSION['redirect_url']); 
        header("Location: $redirect_url");
    } else {
        header("Location: dashboard");
    }
    exit();
}

$error = '';


function getDeviceInfo() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $browser = get_browser_name($user_agent);
    $version = get_browser_version($user_agent);
    $platform = get_operating_system($user_agent);
    return array($browser, $version, $platform);
}

function get_browser_name($user_agent) {
    if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) return 'Opera';
    elseif (strpos($user_agent, 'Edge')) return 'Edge';
    elseif (strpos($user_agent, 'Chrome')) return 'Chrome';
    elseif (strpos($user_agent, 'Safari')) return 'Safari';
    elseif (strpos($user_agent, 'Firefox')) return 'Firefox';
    elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) return 'Internet Explorer';
    return 'Unknown';
}

function get_browser_version($user_agent) {
    $browser_name = get_browser_name($user_agent);
    $known = array('Version', $browser_name, 'other');
    $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
    if (!preg_match_all($pattern, $user_agent, $matches)) {
        return "Unknown";
    }
    
    $i = count($matches['browser']);
    if ($i != 1) {
        $version = $matches['version'][$i - 1];
    } else {
        $version = $matches['version'][0];
    }
    
    return empty($version) ? "Unknown" : $version;
}

function get_operating_system($user_agent) {
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $device_identifier = $_POST['device_identifier'];

    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $login_error = "هذا البريد الإلكتروني غير موجود. قم بتسجيل حساب جديد أو أدخل بريد إلكتروني آخر.";
    } else {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            if ($user['ban'] === 'Yes') {
                $login_error = "هذا المستخدم تم حظره. تواصل معنا لإلغاء الحظر،تواصل معنا 01093867574";
            } elseif ($user['ban'] === 'No') {
                list($browser, $version, $platform) = getDeviceInfo();

                
                if ($user['device'] > $user['allowed_devices']) {
                    $login_error = "لقد تجاوزت الحد الأقصى من الأجهزة المسموح بها. يرجى تسجيل الخروج من جهاز آخر للدخول،تواصل معنا 01093867574";
                } elseif ($user['device'] == $user['allowed_devices']) {
                    
                    $device_stmt = $conn->prepare("SELECT * FROM user_devices WHERE email = ? AND device_identifier = ? AND browser = ? AND version = ?");
                    $device_stmt->bind_param("ssss", $email, $device_identifier, $browser, $version);
                    $device_stmt->execute();
                    $device_result = $device_stmt->get_result();

                    if ($device_result->num_rows > 0) {
                        
                        $device = $device_result->fetch_assoc();
                        $device_idd = $device['idd'];
                        $update_device = $conn->prepare("UPDATE user_devices SET last_login = NOW(), active = 'Yes' WHERE idd = ?");
                        $update_device->bind_param("i", $device_idd);
                        $update_device->execute();
                        loginSuccess($user, $device_idd);
                    } else {
                        $login_error = "هذا المتصفح مستخدم بالفعل في حسابات أخرى. يرجى استخدام متصفح آخر أو تسجيل الخروج من الأجهزة الأخرى،تواصل معنا 01093867574";
                    }
                } else {
                    
                    $device_stmt = $conn->prepare("SELECT * FROM user_devices WHERE email = ? AND device_identifier = ? AND browser = ? AND version = ?");
                    $device_stmt->bind_param("ssss", $email, $device_identifier, $browser, $version);
                    $device_stmt->execute();
                    $device_result = $device_stmt->get_result();

                    if ($device_result->num_rows > 0) {
                        
                        $device = $device_result->fetch_assoc();
                        $device_idd = $device['idd'];
                        $update_device = $conn->prepare("UPDATE user_devices SET last_login = NOW(), active = 'Yes' WHERE idd = ?");
                        $update_device->bind_param("i", $device_idd);
                        $update_device->execute();
                    } else {
                        
                        $insert_device = $conn->prepare("INSERT INTO user_devices (email, device_identifier, browser, version, platform, last_login, active) VALUES (?, ?, ?, ?, ?, NOW(), 'Yes')");
                        $insert_device->bind_param("sssss", $email, $device_identifier, $browser, $version, $platform);
                        $insert_device->execute();
                        $device_idd = $conn->insert_id;

                        
                        $new_device_count = $user['device'] + 1;
                        $update_user = $conn->prepare("UPDATE users SET device = ? WHERE id = ?");
                        $update_user->bind_param("ii", $new_device_count, $user['id']);
                        $update_user->execute();
                    }

                    
                    loginSuccess($user, $device_idd);
                }
            }
        } else {
            $login_error = "البريد الإلكتروني وكلمة المرور غير متطابقين.";
        }
    }

    $stmt->close();
    if (isset($device_stmt)) $device_stmt->close();
}


function loginSuccess($user, $device_idd) {
    global $conn;  

    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['device_idd'] = $device_idd;
    $_SESSION['device_active'] = 'Yes';

    $stmt = $conn->prepare("SELECT year, name ,number FROM users WHERE email = ?");
    $stmt->bind_param("s", $user['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $_SESSION['year'] = $row['year'];
        $_SESSION['user_name'] = $row['name'];
                $_SESSION['number'] = $row['number'];

    }
    
    $stmt->close();

    
    if (isset($_SESSION['redirect_url'])) {
        $redirect_url = $_SESSION['redirect_url'];
        unset($_SESSION['redirect_url']); 
      
        header("Location: $redirect_url");
    } else {
        header("Location: dashboard");
    }
    exit();
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="favicon-32x32.jpg" type="image/jpeg">
    <link rel="icon" type="image/png" sizes="16x16" href="/icon-16.png">
    <meta name="msapplication-TileImage" content="/icon-144.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icon-180.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Aldhiha Exams">
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

        
        .login-form {
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

        input, button {
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
            text-align: left;
        }

        
        @media (max-width: 768px) {
            .login-form {
                padding: 20px;
                border-radius: 10px;
                margin: 0 10px; 
            }

            h1 {
                font-size: 24px;
            }

            input, button {
                font-size: 16px;
                padding: 12px;
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
        .forgot-password {
    text-align: left; 
    margin-bottom: 20px;
    width: 100%; 
}

.forgot-password a {
    color: #673AB7;
    text-decoration: none;
    font-size: 14px;
}

.forgot-password a:hover {
    text-decoration: underline;
}
        
    </style>
</head>
<body>


   
    <div class="login-form">
          <h1>تسجيل الدخول</h1>
        <?php if (!empty($login_error)): ?>
            <div class="error-message"><?php echo $login_error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="البريد الإلكتروني" required>
            <input type="password" name="password" placeholder="كلمة المرور" required>
            <input type="hidden" name="device_identifier" id="device_identifier">
            <div class="forgot-password">
                <a href="forgotpass">هل نسيت كلمة السر؟</a>
            </div>
            <button type="submit">تسجيل الدخول</button>
        </form>
        <div class="options">
            <a href="register" style="font-size: 18px; font-weight: bold; color: #673AB7;">هل تريد إنشاء حساب جديد؟</a>
            <div style="height: 10px;"></div>
        </div>
    </div>

   
    <script>
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

        document.getElementById('device_identifier').value = getDeviceIdentifier();

        
        history.pushState(null, null, location.href);
        window.onpopstate = function(event) {
            history.go(1);
        };

        
        setInterval(function() {
            fetch('check_session.php')
                .then(response => response.json())
                .then(data => {
                    if (data.logged_in) {
                        window.location.href = 'dashboard';
                    }
                });
        }, 5000);

      
        
        document.addEventListener('click', function(e) {
            if (e.target.tagName === 'A') {
                e.preventDefault();
                window.location.href = e.target.href;
            }
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
