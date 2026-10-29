<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | LogiCost SA 2.0</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .about-page {
            margin: 0;
            color: #17352b;
            background: #f7faf8;
            font-family: Arial, Helvetica, sans-serif;
        }
        .about-hero {
            padding: 75px 7%;
            background: linear-gradient(125deg, #e7f5eb, #f8fbf8);
            text-align: center;
        }
        .about-hero h1 {
            margin: 0 0 18px;
            font-size: clamp(34px, 5vw, 52px);
        }
        .about-hero h1 span { color: #14794f; }
        .about-hero p {
            max-width: 760px;
            margin: auto;
            color: #52665b;
            font-size: 18px;
            line-height: 1.8;
        }
        .about-content {
            max-width: 1050px;
            margin: auto;
            padding: 55px 25px;
        }
        .about-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }
        .about-card {
            padding: 27px;
            background: white;
            border: 1px solid #e2ece5;
            border-radius: 15px;
        }
        .about-card h2 { color: #14794f; }
        .about-card p {
            color: #5d7064;
            line-height: 1.8;
        }
        .about-action {
            margin-top: 30px;
            text-align: center;
        }
        .about-action a {
            display: inline-block;
            padding: 14px 20px;
            color: white;
            background: #14794f;
            border-radius: 9px;
            font-weight: bold;
            text-decoration: none;
        }
        @media (max-width: 650px) {
            .about-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="about-page">

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main>
    <section class="about-hero">
        <h1>About <span>LogiCost SA 2.0</span></h1>
        <p>
            Our goal is to make it easier for South African businesses,
            suppliers and independent drivers to connect, discover opportunities
            and manage everyday business operations in one place.
        </p>
    </section>

    <section class="about-content">
        <div class="about-grid">
            <article class="about-card">
                <h2>Our vision</h2>
                <p>
                    To build a practical digital business platform that helps
                    businesses discover services, compare offers and make
                    more informed operational decisions.
                </p>
            </article>

            <article class="about-card">
                <h2>Who we serve</h2>
                <p>
                    Small businesses, suppliers, manufacturers, transport
                    companies, independent drivers and other participating
                    South African businesses.
                </p>
            </article>

            <article class="about-card">
                <h2>What we're building</h2>
                <p>
                    A business marketplace, stock management, delivery tools,
                    location-based features and useful cost reporting.
                </p>
            </article>

            <article class="about-card">
                <h2>Our approach</h2>
                <p>
                    Build reliable features step by step, connect them to real
                    data, protect business accounts and clearly distinguish
                    verified information from user-provided listings.
                </p>
            </article>
        </div>

        <div class="about-action">
            <a href="register.php">Join LogiCost SA 2.0</a>
        </div>
    </section>
</main>

</body>
</html>
