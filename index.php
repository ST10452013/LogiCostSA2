<?php
session_start();

if (!empty($_SESSION['user_id']) && !empty($_SESSION['company_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogiCost SA 2.0 | Smarter Business Connections</title>
    <meta name="description" content="Connect South African businesses, suppliers and independent drivers. Compare offers, manage stock and find cost-effective business solutions.">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .home-page {
            margin: 0;
            color: #17352b;
            background: #f7faf8;
            font-family: Arial, Helvetica, sans-serif;
        }

        .home-nav {
            min-height: 76px;
            padding: 0 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            background: #fff;
            border-bottom: 1px solid #e5eee8;
        }

        .home-logo {
            color: #126b46;
            font-size: 23px;
            font-weight: 800;
            text-decoration: none;
        }

        .home-logo span { color: #17352b; }

        .home-nav-links {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .home-nav a {
            text-decoration: none;
            font-weight: 700;
        }

        .home-link { color: #285443; }

        .home-button {
            display: inline-block;
            padding: 13px 20px;
            color: #fff !important;
            background: #14794f;
            border-radius: 9px;
            text-decoration: none;
            font-weight: 700;
        }

        .home-button:hover { background: #0d603d; }

        .home-hero {
            padding: 88px 7% 80px;
            background: linear-gradient(125deg, #e7f5eb, #f8fbf8 65%, #e0f1e6);
        }

        .home-hero-inner {
            max-width: 1160px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            align-items: center;
            gap: 55px;
        }

        .home-eyebrow {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 12px;
            color: #126b46;
            background: #d4ecdc;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 800;
        }

        .home-hero h1 {
            margin: 0 0 22px;
            font-size: clamp(38px, 5vw, 62px);
            line-height: 1.08;
            letter-spacing: -1.8px;
        }

        .home-hero h1 span { color: #14794f; }

        .home-hero p {
            max-width: 600px;
            color: #52665b;
            font-size: 18px;
            line-height: 1.8;
        }

        .home-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 13px;
            margin-top: 30px;
        }

        .home-button-secondary {
            display: inline-block;
            padding: 12px 19px;
            color: #176746;
            border: 1px solid #b8d5c2;
            border-radius: 9px;
            text-decoration: none;
            font-weight: 700;
        }

        .home-preview {
            padding: 27px;
            background: #fff;
            border: 1px solid #dfebe2;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(22, 70, 46, 0.09);
        }

        .home-preview h2 { margin-top: 0; font-size: 21px; }

        .home-preview-item {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            padding: 17px 0;
            border-bottom: 1px solid #edf2ee;
        }

        .home-preview-item:last-child { border-bottom: 0; }

        .home-icon {
            display: grid;
            flex: 0 0 43px;
            height: 43px;
            place-items: center;
            background: #e6f4e9;
            border-radius: 12px;
            font-size: 21px;
        }

        .home-preview-item strong { display: block; margin: 2px 0 5px; }
        .home-preview-item small { color: #64756a; line-height: 1.5; }

        .home-section {
            max-width: 1160px;
            margin: 0 auto;
            padding: 75px 25px;
        }

        .home-section-heading {
            max-width: 680px;
            margin: 0 auto 35px;
            text-align: center;
        }

        .home-section-heading h2 {
            margin: 0 0 12px;
            font-size: clamp(28px, 4vw, 38px);
        }

        .home-section-heading p {
            color: #637369;
            line-height: 1.7;
        }

        .home-features {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .home-feature {
            padding: 25px;
            background: #fff;
            border: 1px solid #e2ece5;
            border-radius: 15px;
        }

        .home-feature h3 { margin: 17px 0 10px; }
        .home-feature p { color: #637369; line-height: 1.7; }

        .home-cta {
            padding: 48px 25px;
            color: #fff;
            background: #124b35;
            text-align: center;
        }

        .home-cta h2 { font-size: 30px; }
        .home-cta p { color: #d6e9dc; line-height: 1.7; }
        .home-cta .home-button { margin-top: 12px; background: #279765; }

        .home-footer {
            padding: 24px;
            color: #66766c;
            background: #fff;
            text-align: center;
            font-size: 13px;
        }

        @media (max-width: 760px) {
            .home-nav { padding: 17px 5%; align-items: flex-start; }
            .home-nav-links { gap: 12px; flex-wrap: wrap; justify-content: flex-end; }
            .home-nav-links .home-link { display: none; }
            .home-hero { padding: 55px 6%; }
            .home-hero-inner { grid-template-columns: 1fr; gap: 35px; }
            .home-features { grid-template-columns: 1fr; }
            .home-section { padding: 55px 6%; }
        }
    </style>
</head>

<body class="home-page">
    

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main>
    <section class="home-hero" id="about">
        <div class="home-hero-inner">
            <div>
                <span class="home-eyebrow">BUILT FOR SOUTH AFRICAN BUSINESS</span>

                <h1>Spend smarter.<br>Move business <span>forward.</span></h1>

                <p>
                    One platform for businesses, suppliers and independent drivers
                    to connect, compare offers, organise stock and discover
                    more cost-effective ways to work.
                </p>

                <div class="home-hero-actions">
                    <a href="register.php" class="home-button">Create your business account</a>
                    <a href="login.php" class="home-button-secondary">Sign in to your account</a>
                </div>
            </div>

            <div class="home-preview">
                <h2>Your business toolkit</h2>

                <div class="home-preview-item">
                    <div class="home-icon">🤝</div>
                    <div>
                        <strong>Business marketplace</strong>
                        <small>Discover supplier offers and services, then contact businesses to discuss terms.</small>
                    </div>
                </div>

                <div class="home-preview-item">
                    <div class="home-icon">📦</div>
                    <div>
                        <strong>Stock and inventory</strong>
                        <small>Build towards better stock visibility and day-to-day business control.</small>
                    </div>
                </div>

                <div class="home-preview-item">
                    <div class="home-icon">🚚</div>
                    <div>
                        <strong>Drivers and deliveries</strong>
                        <small>Bring transport needs and business connections into one workspace.</small>
                    </div>
                </div>

                <div class="home-preview-item">
                    <div class="home-icon">📍</div>
                    <div>
                        <strong>Map-based tools</strong>
                        <small>Designed for location-aware workflows as mapping services are configured.</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section" id="features">
        <div class="home-section-heading">
            <h2>Everything starts with a better connection</h2>
            <p>
                LogiCost SA 2.0 is being built to help South African businesses
                find opportunities, manage operations and make informed decisions.
            </p>
        </div>

        <div class="home-features">
            <article class="home-feature">
                <div class="home-icon">💰</div>
                <h3>Compare business offers</h3>
                <p>
                    Explore published products and services, compare available
                    prices and contact suppliers about quotations and negotiations.
                </p>
            </article>

            <article class="home-feature">
                <div class="home-icon">📊</div>
                <h3>Organise your operations</h3>
                <p>
                    Work towards a central place for inventory, delivery activity,
                    business information and cost-related records.
                </p>
            </article>

            <article class="home-feature">
                <div class="home-icon">🇿🇦</div>
                <h3>Designed for local businesses</h3>
                <p>
                    Create a business profile with your business type and location
                    in South Africa, and connect with other participating businesses.
                </p>
            </article>
        </div>
    </section>

    <section class="home-cta">
        <h2>Ready to bring your business online?</h2>
        <p>Create an account to access your LogiCost workspace.</p>
        <a href="register.php" class="home-button">Get started</a>
    </section>
</main>

<footer class="home-footer">
    &copy; <?php echo date('Y'); ?> LogiCost SA 2.0. Built for better business connections.
</footer>

</body>
</html>
