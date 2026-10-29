# LogiCost SA – Supply Chain & Costing System


Youtube video link:https://youtu.be/MdfFcV8JVBE?si=U0t-9lvuBzFzA-bK


## 1. Project Overview

LogiCost SA is a logistics, supply chain, and cost-management system designed to support South African manufacturing Small, Medium and Micro Enterprises (SMMEs). The project aims to help businesses manage inventory, coordinate deliveries, compare supplier and service offers, and understand their operating costs through a centralised digital platform.

The planned solution combines a PHP/MySQL web dashboard with a native Android application. Its design prioritises affordability, usability, and the challenges faced by South African businesses, including unreliable electricity and limited internet connectivity.

## 2. Key Features

### Web Dashboard

The web dashboard provides the foundation for business management through the following features:

- **User authentication:** Secure login and registration.
- **Business dashboard:** Central access to business tools and information.
- **Marketplace:** Browse business listings and request quotations.
- **Offers Map:** Explore published marketplace offers using approximate geographical locations.
- **Inventory management:** Support for managing stock and product information.
- **Delivery management:** Support for organising delivery jobs and transport requirements.
- **Cost calculations and reports:** Planned expense recording, delivery-cost calculations, and spending reports.

### Planned Android Application

The proposed Android application will extend the system to mobile users and drivers. Planned functionality includes:

- GPS-based driver tracking and route guidance.
- Barcode scanning for inventory and package verification.
- Offline data access with synchronisation when connectivity returns.
- Biometric authentication where supported.
- Push notifications.
- Photographic proof of delivery and digital signatures.
- Driver delivery and route management.

These mobile features require implementation and testing before they can be considered operational.

## 3. Technology Stack

| Component | Technology |
|---|---|
| Web backend | PHP |
| Database | MySQL |
| Local development environment | XAMPP |
| Web frontend | HTML5, CSS3 and JavaScript |
| Maps | Leaflet and OpenStreetMap |
| Planned Android application | Kotlin and Android Studio |
| Planned mobile database | Room / SQLite |
| Planned API communication | Retrofit and RESTful APIs |
| Planned push notifications | Firebase Cloud Messaging |

The existing web project runs in a local XAMPP environment. Additional technologies listed for the Android application and advanced functionality represent the intended technical direction and must be implemented before being described as completed.

## 4. What Makes LogiCost SA Unique?

LogiCost SA focuses on the practical needs of South African manufacturing businesses rather than assuming that every organisation has reliable electricity, uninterrupted internet access, or the budget for expensive enterprise software.

Its planned offline-first approach will allow supported information to be accessed locally and synchronised when connectivity becomes available. Future load-shedding alerts and delivery-planning improvements could further help businesses prepare for operational disruptions.

The marketplace also aims to help independent drivers, suppliers, and businesses compare available offers and request quotations, encouraging more informed purchasing and service decisions.

## 5. Impact and Scalability

LogiCost SA aims to reduce reliance on manual business processes, improve the organisation of inventory and delivery information, and help businesses make better cost-related decisions.

Its modular structure provides opportunities for future enhancements, including automated costing, bills of materials (BOM), landed-cost calculations, VAT reporting, demand forecasting, and fleet analytics. These capabilities could be introduced progressively as the system develops.

By supporting more organised workflows and improving access to business information, LogiCost SA has the potential to help South African manufacturing SMMEs improve efficiency, control operating costs, and support sustainable growth.

## 6. Project Team

| Team member | Student number | Role |
|---|---|---|
| Lethabo Mdhluli | ST10447479 | Project Manager / App Developer |
| Tumelo Matlou | ST10452013 | Business Analyst / Website Developer |
| Modise Mashishi | ST10447481 | Software Developer / Presenter |

## 7. Development Environment and Setup

The current web application is developed locally using XAMPP.

1. Install XAMPP and start Apache and MySQL.
2. Place the project folder inside the XAMPP `htdocs` directory.
3. Import or configure the required project database using phpMyAdmin.
4. Check the database connection settings in `config/database.php`.
5. Open the application through the appropriate `http://localhost/` project address.
6. Sign in using a valid registered account to access protected dashboard features.

The current development project uses PHP 8.1.25. The PHP version and required extensions should be checked before deployment to another environment.

## 8. Current Development Status

The web project includes working login and registration functionality, session-based authentication, a shared navigation structure, a business dashboard, marketplace functionality, and an Offers Map foundation.

Inventory enhancements, advanced costing and reporting, genuine live GPS tracking, load-shedding integrations, and the Android application require further implementation and testing. Map markers based on city information are approximate and do not represent verified live vehicle locations.

**Project goal:** To develop a practical, affordable, and scalable supply chain and costing platform that addresses the needs of South African manufacturing SMMEs.


Modise is a collaborator
Lethabo is a collaborator
