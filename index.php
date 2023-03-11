<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage - Student Management System</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <!--FONT AWESOME-->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

<!--GOOGLE FONTS-->
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@100;200;400;500;600;700;800;900&family=Mukta:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet" />

<!--PLUGIN-->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick.min.css">
	<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick-theme.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick.min.js"></script>

</head>

<body>

<!--NAV-->
<nav>
    <section class="flex_content">
        <figure class="logo fixed_flex">
            <img src="assets/images/logo.jpg" alt="">
            <figcaption>
                <strong class="title">WSB</strong> Student Management
            </figcaption>
        </figure>
    </section>
    <section class="flex_content nav_content" id="nav_content">
        <a href="#" class="active">Home</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="#about">About us</a>
    </section>
    <section class="flex_content">
        <a href="javascript:void(0)" class="ham"><i class="fa fa-bars"></i></a>
    </section>
</nav>

<!--MENU-->
<menu id="menu" class="side_menu">
    <a href="javascript:void(0)" class="close"><i class="fa fa-times"></i></a>
    <strong class="fixed_flex logo"><img src="assets/images/logo.jpg" alt="Summit"  loading="lazy" /></strong>
    <br>
    <ul>
        <li><a href="#">Home</a></li>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="#">About us</a></li>
        <li class="fixed_flex"><a href="auth/login.php" class="btn btn_1 chat_popup">SignUp/LogIn</a> </li>
    </ul>
</menu>


<!--HEADER-->
<header class="flex">
    <article>
        <h1 class="title big">Welcome to <br><em>WSB</em> School Management</h1>
        <p>Welcome to WSB School Management System, where we streamline the educational experience for students, parents, and educators alike. Our platform offers a comprehensive suite of tools designed to enhance communication, simplify administrative tasks, and foster a collaborative learning environment. From real-time notifications to easy access to academic resources, we are committed to providing a seamless and efficient experience for everyone involved in the educational journey.</p>
        <a href="#" class="btn btn_3">Explore more</a>
    </article>
    <section class="flex">
        <aside class="padding_1x">
            <h2 class="sub_title">Register Student</h2>
            <p>Our registration process is streamlined to ensure a smooth experience for both students and parents. Sign up today to join our community!</p>
            <a href="#"><i class="fa fa-angle-right"></i></a>
        </aside>
        <aside class="padding_1x">
            <h2 class="sub_title">Monitor Attendance</h2>
            <p>Stay informed about student attendance with our real-time monitoring system. Easily track attendance records and ensure accountability.</p>
            <a href="#"><i class="fa fa-angle-right"></i></a>
        </aside>
        <aside class="padding_1x">
            <h2 class="sub_title">Generate Reports</h2>
            <p>Generate comprehensive reports on student performance and attendance. Our reporting tools provide valuable insights for educators and parents.</p>
            <a href="#"><i class="fa fa-angle-right"></i></a>
        </aside>
    </section>
</header>



<!--FOOTER-->
<footer class="padding_4x">
    <div class="top_footer flex">
        <section class="flex_content">
            <figure>
                <img src="assets/images/logo2.jpg" alt="" loading="lazy" />
            </figure>
        </section>
        <section class="flex_content padding_4x">
            <h2 class="title medium">Principal Message</h2>
            <p>"It is easier to build strong children than to repair broken men". A wise quote referring to the role that children could play in laying the foundation of a strong nation. I believe that the foundation of a strong nation depends on the way its children are cared for and nurtured. In order to build a strong nation, we must instill good values in our children providing them love and care, guiding them through thick and thin till they become confident and strong enough. This all can be done through "Quality education".</p>
        </section>
    </div>
  <div class="footer_body flex">
    <section class="flex_content padding_1x">
      <figure class="logo fixed_flex">
            <img src="assets/images/logo.jpg" alt="">
            <figcaption>
                <strong class="title">WSB</strong>Student Management
            </figcaption>
        </figure>
        
        <a href="emailto:info@schotest.com">
            <i class="fa fa-envelope-o"></i>
            info@WSB.com
        </a>
        <a href="tel:12345678">
            <i class="fa fa-headphones"></i>
            12345678
        </a>
    </section>
    <section class="flex_content padding_1x">
      <h3>Quick Links</h3>
      <a href="index.php">Home</a>
      <a href="dashboard.php">Dashboard</a>
      <a href="auth/login.php">Login</a>
      <a href="auth/register.php">Register</a>
    </section>

  </div>
  <div class="flex">
    <section class="flex-content padding_1x">
      <p>Copyright ©2025 All rights reserved, <b> WSB </b> Student Management</p>
    </section>
  </div>
</footer>


<!--ADDITIONAL-->
<div class="overlay"></div>
<div class="cursor"></div>

<script src="./scripts/home.js"></script>
</body>

</html>