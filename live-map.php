<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Map | LogiCost SA 2.0</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    >

    <style>
        .map-page {
            margin: 0;
            min-height: 100vh;
            background: #f5f8f6;
            color: #17352b;
            font-family: Arial, Helvetica, sans-serif;
        }

        .map-content {
            max-width: 1450px;
            margin: auto;
            padding: 30px 4%;
        }

        .map-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .map-heading h1 {
            margin: 0 0 9px;
            font-size: clamp(27px, 4vw, 36px);
        }

        .map-heading p {
            margin: 0;
            color: #637369;
            line-height: 1.6;
        }

        .demo-banner {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            padding: 17px 19px;
            margin-bottom: 22px;
            background: #fff8e7;
            border: 1px solid #f0d99b;
            border-left: 5px solid #d49a16;
            border-radius: 12px;
        }

        .demo-banner-icon {
            font-size: 23px;
            line-height: 1.2;
        }

        .demo-banner strong {
            display: block;
            margin-bottom: 5px;
            color: #74500b;
        }

        .demo-banner p {
            margin: 0;
            color: #78643b;
            font-size: 13px;
            line-height: 1.6;
        }

        .map-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 22px;
        }

        .map-stat {
            padding: 18px;
            background: #fff;
            border: 1px solid #e0e9e3;
            border-radius: 13px;
            box-shadow: 0 5px 18px rgba(18, 75, 53, .04);
        }

        .map-stat-label {
            margin-bottom: 9px;
            color: #68796e;
            font-size: 13px;
        }

        .map-stat-value {
            color: #14794f;
            font-size: 28px;
            font-weight: 800;
        }

        .map-stat small {
            display: block;
            margin-top: 5px;
            color: #849087;
            font-size: 11px;
        }

        .map-panel {
            overflow: hidden;
            background: #fff;
            border: 1px solid #e0e9e3;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(18, 75, 53, .06);
        }

        .map-panel-heading {
            padding: 19px 20px 0;
        }

        .map-panel-heading h2 {
            margin: 0 0 6px;
            font-size: 19px;
        }

        .map-panel-heading p {
            margin: 0;
            color: #718075;
            font-size: 13px;
            line-height: 1.6;
        }

        .map-toolbar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 17px;
            border-bottom: 1px solid #e7eee9;
        }

        .map-button {
            padding: 12px 17px;
            border: 0;
            border-radius: 9px;
            background: #14794f;
            color: white;
            font-weight: 700;
            cursor: pointer;
        }

        .map-button:hover {
            background: #0d603d;
        }

        .map-button:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .map-button-secondary {
            background: #edf5ef;
            color: #176746;
        }

        .map-button-secondary:hover {
            background: #dcefe1;
        }

        .map-status {
            flex: 1 1 250px;
            color: #53675a;
            font-size: 14px;
            line-height: 1.5;
        }

        .map-status strong {
            color: #14794f;
        }

        .map-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            padding: 13px 18px;
            color: #617166;
            font-size: 12px;
            border-bottom: 1px solid #e7eee9;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .legend-line {
            width: 24px;
            height: 4px;
            border-radius: 5px;
            background: #168653;
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #168653;
        }

        #business-map {
            width: 100%;
            height: min(65vh, 650px);
            min-height: 360px;
            background: #e8eee9;
        }

        .map-note {
            padding: 15px 18px;
            color: #617166;
            font-size: 13px;
            line-height: 1.7;
            background: #fff;
        }

        .map-note strong {
            color: #17352b;
        }

        .fleet-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 30px 0 15px;
        }

        .fleet-heading h2 {
            margin: 0;
            font-size: 22px;
        }

        .demo-chip {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #fff1ce;
            color: #78540c;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .3px;
        }

        .fleet-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .fleet-card {
            padding: 19px;
            background: #fff;
            border: 1px solid #e0e9e3;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(18, 75, 53, .04);
        }

        .fleet-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 17px;
        }

        .fleet-vehicle {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .fleet-icon {
            display: flex;
            flex: 0 0 45px;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: #e9f5ed;
            border-radius: 12px;
            font-size: 24px;
        }

        .fleet-vehicle h3 {
            margin: 0 0 5px;
            font-size: 15px;
        }

        .fleet-vehicle p {
            margin: 0;
            color: #7a887e;
            font-size: 12px;
        }

        .fleet-status {
            padding: 6px 9px;
            border-radius: 20px;
            background: #e7f6ec;
            color: #167044;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .fleet-route {
            margin-bottom: 15px;
            color: #53675a;
            font-size: 13px;
            line-height: 1.7;
        }

        .fleet-route strong {
            color: #17352b;
        }

        .fleet-progress-label {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            color: #718075;
            font-size: 12px;
        }

        .fleet-progress-label strong {
            color: #14794f;
        }

        .fleet-progress-track {
            width: 100%;
            height: 8px;
            overflow: hidden;
            background: #e9efeb;
            border-radius: 20px;
        }

        .fleet-progress-fill {
            width: 0;
            height: 100%;
            background: #168653;
            border-radius: 20px;
            transition: width .15s linear;
        }

        .fleet-card-bottom {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
            padding-top: 13px;
            border-top: 1px solid #edf1ee;
            color: #728075;
            font-size: 12px;
        }

        .fleet-card-bottom strong {
            color: #17352b;
        }

        .fleet-focus-button {
            margin-top: 14px;
            padding: 9px 12px;
            border: 1px solid #d5e8da;
            border-radius: 8px;
            background: #f3f8f4;
            color: #176746;
            font-weight: 700;
            cursor: pointer;
        }

        .fleet-focus-button:hover {
            background: #e2f0e6;
        }

        .map-attribution-note {
            margin-top: 17px;
            color: #839087;
            font-size: 11px;
            line-height: 1.7;
        }

        .vehicle-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 39px;
            height: 39px;
            border: 3px solid #fff;
            border-radius: 50%;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .25);
            font-size: 20px;
        }

        .vehicle-popup {
            min-width: 155px;
            line-height: 1.7;
        }

        .vehicle-popup strong {
            color: #14794f;
        }

        @media (max-width: 760px) {
            .map-stats {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .map-stat {
                padding: 14px 16px;
            }

            .map-stat-value {
                font-size: 24px;
            }

            .fleet-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .map-content {
                padding: 22px 4%;
            }

            #business-map {
                height: 55vh;
                min-height: 320px;
            }

            .map-toolbar {
                align-items: stretch;
            }

            .map-button {
                width: 100%;
            }

            .map-status {
                flex-basis: 100%;
            }

            .fleet-card-top {
                flex-wrap: wrap;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .fleet-progress-fill {
                transition: none;
            }
        }
    </style>
</head>

<body class="map-page">

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="map-content">

    <header class="map-heading">
        <div>
            <h1>Live map &amp; fleet tracking</h1>
            <p>
                Explore the map, view illustrative delivery routes and use
                your device's location if you choose.
            </p>
        </div>
    </header>

    <section class="demo-banner" aria-label="Demo tracking notice">
        <div class="demo-banner-icon" aria-hidden="true">⚠️</div>
        <div>
            <strong>DEMO — SIMULATED TRACKING</strong>
            <p>
                The animated vehicles, routes, progress percentages and
                estimated times below are demonstrations only. They do not
                represent real drivers, actual deliveries or live fleet GPS.
                Your device location is separate and is shown only if you
                explicitly enable it.
            </p>
        </div>
    </section>

    <section class="map-stats" aria-label="Demo fleet summary">
        <article class="map-stat">
            <div class="map-stat-label">Demo vehicles</div>
            <div class="map-stat-value" id="demo-vehicle-count">4</div>
            <small>Simulated markers on this map</small>
        </article>

        <article class="map-stat">
            <div class="map-stat-label">Demo routes</div>
            <div class="map-stat-value" id="demo-route-count">4</div>
            <small>Illustrative routes, not road navigation</small>
        </article>

        <article class="map-stat">
            <div class="map-stat-label">Animation</div>
            <div class="map-stat-value" id="animation-status">Running</div>
            <small>Demo animation in this browser session</small>
        </article>
    </section>

    <section class="map-panel" aria-label="Interactive fleet map">
        <div class="map-panel-heading">
            <h2>South Africa fleet overview</h2>
            <p>
                Select a vehicle below to centre the map on its simulated
                position.
            </p>
        </div>

        <div class="map-toolbar">
            <button class="map-button" id="start-location" type="button">
                Show my location
            </button>

            <button
                class="map-button map-button-secondary"
                id="stop-location"
                type="button"
                disabled
            >
                Stop location
            </button>

            <div class="map-status" id="location-status" role="status"
                 aria-live="polite">
                <strong>Device location off.</strong>
                Demo vehicles are moving independently of your location.
            </div>
        </div>

        <div class="map-legend" aria-label="Map legend">
            <span class="legend-item">
                <span class="legend-line"></span>
                Simulated route
            </span>
            <span class="legend-item">
                <span class="legend-dot"></span>
                Animated demo vehicle
            </span>
            <span class="legend-item">
                <span aria-hidden="true">📍</span>
                Your device location, if enabled
            </span>
        </div>

        <div id="business-map" aria-label="Interactive map of South Africa"></div>

        <div class="map-note">
            <strong>Privacy:</strong> device location starts only after you
            press the button and grant browser permission. This page displays
            your location in your browser and does not save your GPS coordinates
            to the database or share them with other users.

            <br><br>

            <strong>Map accuracy:</strong> the demo routes are illustrative
            lines between locations, not verified driving routes. Vehicle
            positions, progress and demo ETAs are generated in your browser
            and reset when the page is refreshed.
        </div>
    </section>

    <section aria-labelledby="fleet-heading">
        <div class="fleet-heading">
            <h2 id="fleet-heading">Demo delivery vehicles</h2>
            <span class="demo-chip">SIMULATION ONLY</span>
        </div>

        <div class="fleet-grid" id="fleet-grid">

            <article class="fleet-card" data-vehicle-card="0">
                <div class="fleet-card-top">
                    <div class="fleet-vehicle">
                        <div class="fleet-icon" aria-hidden="true">🚚</div>
                        <div>
                            <h3>Demo vehicle LC-101</h3>
                            <p>Johannesburg → Durban</p>
                        </div>
                    </div>
                    <span class="fleet-status">Simulated movement</span>
                </div>

                <div class="fleet-route">
                    <strong>Route:</strong> Gauteng to KwaZulu-Natal
                </div>

                <div class="fleet-progress-label">
                    <span>Demo route progress</span>
                    <strong data-progress-label="0">0%</strong>
                </div>
                <div class="fleet-progress-track">
                    <div class="fleet-progress-fill"
                         data-progress-bar="0"></div>
                </div>

                <div class="fleet-card-bottom">
                    <span>Demo ETA: <strong>~2 hours</strong></span>
                    <span>Position: <strong data-position="0">Updating…</strong></span>
                </div>

                <button class="fleet-focus-button"
                        type="button" data-focus-vehicle="0">
                    View vehicle on map
                </button>
            </article>

            <article class="fleet-card" data-vehicle-card="1">
                <div class="fleet-card-top">
                    <div class="fleet-vehicle">
                        <div class="fleet-icon" aria-hidden="true">🚛</div>
                        <div>
                            <h3>Demo vehicle LC-202</h3>
                            <p>Cape Town → Gqeberha</p>
                        </div>
                    </div>
                    <span class="fleet-status">Simulated movement</span>
                </div>

                <div class="fleet-route">
                    <strong>Route:</strong> Western Cape to Eastern Cape
                </div>

                <div class="fleet-progress-label">
                    <span>Demo route progress</span>
                    <strong data-progress-label="1">0%</strong>
                </div>
                <div class="fleet-progress-track">
                    <div class="fleet-progress-fill"
                         data-progress-bar="1"></div>
                </div>

                <div class="fleet-card-bottom">
                    <span>Demo ETA: <strong>~3 hours</strong></span>
                    <span>Position: <strong data-position="1">Updating…</strong></span>
                </div>

                <button class="fleet-focus-button"
                        type="button" data-focus-vehicle="1">
                    View vehicle on map
                </button>
            </article>

            <article class="fleet-card" data-vehicle-card="2">
                <div class="fleet-card-top">
                    <div class="fleet-vehicle">
                        <div class="fleet-icon" aria-hidden="true">🚚</div>
                        <div>
                            <h3>Demo vehicle LC-303</h3>
                            <p>Bloemfontein → Johannesburg</p>
                        </div>
                    </div>
                    <span class="fleet-status">Simulated movement</span>
                </div>

                <div class="fleet-route">
                    <strong>Route:</strong> Free State to Gauteng
                </div>

                <div class="fleet-progress-label">
                    <span>Demo route progress</span>
                    <strong data-progress-label="2">0%</strong>
                </div>
                <div class="fleet-progress-track">
                    <div class="fleet-progress-fill"
                         data-progress-bar="2"></div>
                </div>

                <div class="fleet-card-bottom">
                    <span>Demo ETA: <strong>~90 minutes</strong></span>
                    <span>Position: <strong data-position="2">Updating…</strong></span>
                </div>

                <button class="fleet-focus-button"
                        type="button" data-focus-vehicle="2">
                    View vehicle on map
                </button>
            </article>

            <article class="fleet-card" data-vehicle-card="3">
                <div class="fleet-card-top">
                    <div class="fleet-vehicle">
                        <div class="fleet-icon" aria-hidden="true">🚛</div>
                        <div>
                            <h3>Demo vehicle LC-404</h3>
                            <p>Pretoria → Polokwane</p>
                        </div>
                    </div>
                    <span class="fleet-status">Simulated movement</span>
                </div>

                <div class="fleet-route">
                    <strong>Route:</strong> Gauteng to Limpopo
                </div>

                <div class="fleet-progress-label">
                    <span>Demo route progress</span>
                    <strong data-progress-label="3">0%</strong>
                </div>
                <div class="fleet-progress-track">
                    <div class="fleet-progress-fill"
                         data-progress-bar="3"></div>
                </div>

                <div class="fleet-card-bottom">
                    <span>Demo ETA: <strong>~75 minutes</strong></span>
                    <span>Position: <strong data-position="3">Updating…</strong></span>
                </div>

                <button class="fleet-focus-button"
                        type="button" data-focus-vehicle="3">
                    View vehicle on map
                </button>
            </article>

        </div>

        <p class="map-attribution-note">
            All vehicle names, movement, route progress and ETA examples are
            fictional demonstration data. The map tiles are provided by
            OpenStreetMap contributors.
        </p>
    </section>

</main>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>

<script>
(function () {
    'use strict';

    const mapElement = document.getElementById('business-map');
    const statusElement = document.getElementById('location-status');
    const startButton = document.getElementById('start-location');
    const stopButton = document.getElementById('stop-location');
    const animationStatus = document.getElementById('animation-status');

    if (!window.L) {
        statusElement.textContent =
            'The map library could not load. Check your internet connection and refresh.';
        startButton.disabled = true;
        animationStatus.textContent = 'Unavailable';
        return;
    }

    // Initial view is South Africa, not a claimed device location.
    const map = L.map(mapElement).setView([-29.0, 24.0], 5);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    /*
     * DEMO DATA ONLY.
     *
     * These waypoint sequences are illustrative, not actual road geometry.
     * Vehicle markers move along the lines in the browser.
     */
    const vehicles = [
        {
            id: 'LC-101',
            from: 'Johannesburg',
            to: 'Durban',
            color: '#168653',
            duration: 32000,
            offset: 0.08,
            route: [
                [-26.2041, 28.0473],
                [-27.0, 28.2],
                [-28.1, 29.0],
                [-29.0, 29.6],
                [-29.8587, 31.0218]
            ],
            marker: null,
            line: null
        },
        {
            id: 'LC-202',
            from: 'Cape Town',
            to: 'Gqeberha',
            color: '#2468bd',
            duration: 42000,
            offset: 0.36,
            route: [
                [-33.9249, 18.4241],
                [-34.1, 20.0],
                [-33.8, 22.0],
                [-33.5, 24.0],
                [-33.9608, 25.6022]
            ],
            marker: null,
            line: null
        },
        {
            id: 'LC-303',
            from: 'Bloemfontein',
            to: 'Johannesburg',
            color: '#b46a14',
            duration: 28000,
            offset: 0.62,
            route: [
                [-29.0852, 26.1596],
                [-28.5, 26.7],
                [-27.5, 27.4],
                [-26.8, 27.8],
                [-26.2041, 28.0473]
            ],
            marker: null,
            line: null
        },
        {
            id: 'LC-404',
            from: 'Pretoria',
            to: 'Polokwane',
            color: '#8c55b9',
            duration: 35000,
            offset: 0.78,
            route: [
                [-25.7479, 28.2293],
                [-25.2, 28.4],
                [-24.7, 28.6],
                [-24.0, 29.1],
                [-23.9045, 29.4689]
            ],
            marker: null,
            line: null
        }
    ];

    document.getElementById('demo-vehicle-count').textContent =
        String(vehicles.length);

    document.getElementById('demo-route-count').textContent =
        String(vehicles.length);

    function makeVehicleIcon(color) {
        return L.divIcon({
            className: '',
            html:
                '<div class="vehicle-marker" style="background:' +
                color +
                ';" aria-label="Simulated vehicle">🚚</div>',
            iconSize: [39, 39],
            iconAnchor: [19, 19]
        });
    }

    function interpolateRoute(route, progress) {
        const segmentCount = route.length - 1;
        const scaledProgress = Math.max(
            0,
            Math.min(0.999999, progress)
        ) * segmentCount;

        const segmentIndex = Math.floor(scaledProgress);
        const segmentProgress = scaledProgress - segmentIndex;

        const start = route[segmentIndex];
        const end = route[segmentIndex + 1];

        return [
            start[0] + (end[0] - start[0]) * segmentProgress,
            start[1] + (end[1] - start[1]) * segmentProgress
        ];
    }

    function makePopup(vehicle) {
        return (
            '<div class="vehicle-popup">' +
            '<strong>' + vehicle.id + '</strong><br>' +
            vehicle.from + ' → ' + vehicle.to + '<br>' +
            '<span style="color:#9a6a12;font-weight:700;">' +
            'DEMO POSITION ONLY</span>' +
            '</div>'
        );
    }

    // Draw each illustrative route and add its animated marker.
    vehicles.forEach(function (vehicle) {
        vehicle.line = L.polyline(vehicle.route, {
            color: vehicle.color,
            weight: 4,
            opacity: 0.75,
            dashArray: '8 8'
        }).addTo(map);

        const initialPoint = interpolateRoute(
            vehicle.route,
            vehicle.offset
        );

        vehicle.marker = L.marker(initialPoint, {
            icon: makeVehicleIcon(vehicle.color),
            keyboard: true,
            title: 'Simulated vehicle ' + vehicle.id
        }).addTo(map);

        vehicle.marker.bindPopup(makePopup(vehicle));

        const focusButton = document.querySelector(
            '[data-focus-vehicle="' +
            vehicles.indexOf(vehicle) +
            '"]'
        );

        if (focusButton) {
            focusButton.addEventListener('click', function () {
                const point = vehicle.marker.getLatLng();

                map.setView(point, 8, { animate: true });
                vehicle.marker.openPopup();
            });
        }
    });

    // Show all demonstration routes on the initial map.
    const allRoutePoints = [];

    vehicles.forEach(function (vehicle) {
        vehicle.route.forEach(function (point) {
            allRoutePoints.push(point);
        });
    });

    map.fitBounds(L.latLngBounds(allRoutePoints), {
        padding: [28, 28],
        maxZoom: 6
    });

    let animationFrame = null;
    let animationStartTime = null;
    let watchId = null;
    let userMarker = null;
    let accuracyCircle = null;
    let hasCenteredOnUser = false;

    const prefersReducedMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function updateVehicleCards(index, progress, point) {
        const percentage = Math.round(progress * 100);

        const progressLabel = document.querySelector(
            '[data-progress-label="' + index + '"]'
        );

        const progressBar = document.querySelector(
            '[data-progress-bar="' + index + '"]'
        );

        const positionLabel = document.querySelector(
            '[data-position="' + index + '"]'
        );

        if (progressLabel) {
            progressLabel.textContent = percentage + '%';
        }

        if (progressBar) {
            progressBar.style.width = percentage + '%';
        }

        if (positionLabel) {
            positionLabel.textContent =
                point[0].toFixed(2) + ', ' + point[1].toFixed(2);
        }
    }

    function animateVehicles(timestamp) {
        if (animationStartTime === null) {
            animationStartTime = timestamp;
        }

        vehicles.forEach(function (vehicle, index) {
            const elapsed = timestamp - animationStartTime;

            const progress = (
                (elapsed / vehicle.duration + vehicle.offset) % 1
            );

            const point = interpolateRoute(vehicle.route, progress);

            vehicle.marker.setLatLng(point);

            updateVehicleCards(index, progress, point);
        });

        animationFrame = window.requestAnimationFrame(animateVehicles);
    }

    function startDemoAnimation() {
        if (animationFrame !== null) {
            return;
        }

        animationStartTime = null;
        animationStatus.textContent = 'Running';

        animationFrame = window.requestAnimationFrame(animateVehicles);
    }

    function stopDemoAnimation() {
        if (animationFrame !== null) {
            window.cancelAnimationFrame(animationFrame);
        }

        animationFrame = null;
        animationStartTime = null;
        animationStatus.textContent = 'Paused';
    }

    // Reduce motion when the visitor has requested it in device settings.
    if (prefersReducedMotion) {
        animationStatus.textContent = 'Reduced motion';

        vehicles.forEach(function (vehicle, index) {
            const point = interpolateRoute(vehicle.route, vehicle.offset);

            vehicle.marker.setLatLng(point);
            updateVehicleCards(index, vehicle.offset, point);
        });
    } else {
        startDemoAnimation();
    }

    function setStatus(message) {
        statusElement.textContent = message;
    }

    function stopWatching(showMessage = true) {
        if (watchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(watchId);
        }

        watchId = null;
        startButton.disabled = false;
        stopButton.disabled = true;

        if (showMessage) {
            setStatus(
                'Device location tracking stopped. Your last marker remains visible until you refresh or start again.'
            );
        }
    }

    function handleLocation(position) {
        const latitude = position.coords.latitude;
        const longitude = position.coords.longitude;
        const accuracy = position.coords.accuracy;

        if (
            !Number.isFinite(latitude) ||
            !Number.isFinite(longitude) ||
            !Number.isFinite(accuracy)
        ) {
            setStatus('Your device returned invalid location coordinates.');
            return;
        }

        const point = [latitude, longitude];

        if (!userMarker) {
            userMarker = L.marker(point, {
                title: 'Your device location'
            })
            .addTo(map)
            .bindPopup('Your device location — not demo vehicle data');
        } else {
            userMarker.setLatLng(point);
        }

        if (!accuracyCircle) {
            accuracyCircle = L.circle(point, {
                radius: accuracy,
                color: '#14794f',
                fillColor: '#4caf79',
                fillOpacity: 0.15,
                weight: 2
            }).addTo(map);
        } else {
            accuracyCircle.setLatLng(point);
            accuracyCircle.setRadius(accuracy);
        }

        if (!hasCenteredOnUser) {
            map.setView(point, 15);
            userMarker.openPopup();
            hasCenteredOnUser = true;
        }

        setStatus(
            'Your device location is active. Approximate accuracy: ' +
            Math.round(accuracy) +
            ' metres. Last update: ' +
            new Date(position.timestamp).toLocaleTimeString() +
            '. Demo vehicles are simulated separately.'
        );
    }

    function handleLocationError(error) {
        let message;

        switch (error.code) {
            case error.PERMISSION_DENIED:
                message =
                    'Location permission was denied. Allow location access in your browser settings to try again.';
                break;

            case error.POSITION_UNAVAILABLE:
                message =
                    'Your device could not determine a location. Check GPS or location services and try again.';
                break;

            case error.TIMEOUT:
                message =
                    'The location request timed out. Try again when your location signal is stronger.';
                break;

            default:
                message = 'An unknown location error occurred.';
        }

        stopWatching(false);
        setStatus(message);
    }

    startButton.addEventListener('click', function () {
        if (!('geolocation' in navigator)) {
            setStatus('This browser does not support device geolocation.');
            return;
        }

        if (watchId !== null) {
            return;
        }

        hasCenteredOnUser = false;
        startButton.disabled = true;
        stopButton.disabled = false;

        setStatus(
            'Waiting for your permission and your device GPS signal…'
        );

        watchId = navigator.geolocation.watchPosition(
            handleLocation,
            handleLocationError,
            {
                enableHighAccuracy: true,
                maximumAge: 5000,
                timeout: 20000
            }
        );
    });

    stopButton.addEventListener('click', function () {
        stopWatching(true);
    });

    // Refresh Leaflet sizing after layout changes.
    window.addEventListener('resize', function () {
        map.invalidateSize();
    });

    window.setTimeout(function () {
        map.invalidateSize();
    }, 200);

    // Stop the animation when the page is hidden to conserve resources.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            if (!prefersReducedMotion) {
                stopDemoAnimation();
            }
        } else if (!prefersReducedMotion) {
            startDemoAnimation();
        }
    });

    // Remove the device location watcher when navigating away.
    window.addEventListener('pagehide', function () {
        if (watchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }

        if (animationFrame !== null) {
            window.cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
    });
})();
</script>

</body>
</html>
