<?php
session_start();
require_once("../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $error = "All fields are required.";
    } else {

        $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $hashed_password);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                $_SESSION["admin_id"] = $id;
                
                // LOG LOGIN
                require_once("../config/audit_helper.php");
                logActivity($conn, 'LOGIN', 'ADMIN', $id, "Admin logged in: $username");

                header("Location: ../dashboard.php");
                exit;
            } else {
                $error = "Invalid credentials.";
            }
        } else {
            $error = "Invalid credentials.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../style/login.css">
</head>
<body>

  <nav>
    <img src="https://enrollment.cefi.website/images/cefi-logo.png" alt="cefi-logo" loading="lazy">
    <div class="logo">CEFI ONLINE FACILITY RESERVATION</div>
  </nav>

    <form method="POST" class="loginForm">
        <h2>Admin Login</h2>

	<div class="input-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" placeholder="Enter username" required>
        </div>

       <div class="input-group">
          <label for="password">Password</label>
          <div class="password-wrapper">
            <input type="password" id="password" name="password" placeholder="Enter password" required>
            <span class="toggle-password" id="togglePassword">👁️</span>
          </div>
        </div>
        
        <button type="submit">Login</button>

        <?php if(isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
    </form>

  <div class="footer">
    © 2026 CEFI ONLINE FACILITY RESERVATION. All rights reserved. | Calayan Educational Foundation Inc., Philippines | Contact: info@cefi.website
  </div>
  <script>
    const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#password');

togglePassword.addEventListener('click', function () {
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    
    this.textContent = type === 'password' ? '👁️' : '🙈';

    if (type === 'text') {
        setTimeout(() => {
            password.setAttribute('type', 'password');
            togglePassword.textContent = '👁️';
        }, 3000); 
    }
});

</script>
</body>
</html>
