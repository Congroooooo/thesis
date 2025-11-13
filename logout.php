<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <script>
        // Clear policy session storage on logout
        if (typeof(Storage) !== "undefined") {
            sessionStorage.removeItem("policyShown");
        }
        // Redirect to homepage
        window.location.href = "index.php";
    </script>
</head>
<body>
</body>
</html> 