<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NakBurger - Find Street Burger Stalls Near You</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Leaflet CSS for Interactive Maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #fdfbf7;
        }
        
        .brand-text {
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        #map-container {
            height: calc(100vh - 76px);
            position: sticky;
            top: 76px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        #map {
            height: 100%;
            width: 100%;
        }

        .list-container {
            height: calc(100vh - 76px);
            overflow-y: auto;
            padding-right: 8px;
        }

        /* Custom Scrollbar */
        .list-container::-webkit-scrollbar {
            width: 6px;
        }
        .list-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .list-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .stall-card {
            border: 2px solid transparent;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border-radius: 16px;
        }

        .stall-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(220, 100, 0, 0.1) !important;
            border-color: #ffc107;
        }

        .stall-card.active-stall {
            border-color: #ff9f1c;
            background-color: #fff9f0 !important;
        }

        .badge-amber {
            background-color: #ffe8cc;
            color: #d97706;
            font-weight: 600;
        }

        .badge-red {
            background-color: #fee2e2;
            color: #dc2626;
            font-weight: 600;
        }

        .custom-marker-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #ff9f1c;
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            color: white;
            font-size: 16px;
            width: 36px;
            height: 36px;
            transition: all 0.3s ease;
        }

        .custom-marker-icon.user-marker {
            background-color: #0d6efd;
            animation: pulse-blue 2s infinite;
        }

        @keyframes pulse-blue {
            0% {
                box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(13, 110, 253, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
            }
        }

        .menu-item-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 12px;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        /* Order Tracker Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 11px;
            top: 5px;
            bottom: 5px;
            width: 2px;
            background: #e5e7eb;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #cbd5e1;
            border: 2px solid white;
            box-shadow: 0 0 0 4px #f1f5f9;
            transition: all 0.3s ease;
        }
        .timeline-item.active::before {
            background: #ff9f1c;
            box-shadow: 0 0 0 4px #ffedd5;
            transform: scale(1.2);
        }
        .timeline-item.completed::before {
            background: #10b981;
            box-shadow: 0 0 0 4px #d1fae5;
        }

        /* Responsive height limits */
        @media (max-width: 991.98px) {
            #map-container {
                height: 350px;
                position: relative;
                top: 0;
                margin-bottom: 20px;
            }
            .list-container {
                height: auto;
                overflow-y: visible;
            }
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top py-3">
        <div class="container-fluid px-lg-5">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <span class="bg-warning text-dark px-3 py-2 rounded-3 me-2 d-inline-block shadow-sm">
                    <i class="bi bi-fire"></i>
                </span>
                <span class="brand-text text-white fs-4">Nak<span class="text-warning">Burger</span></span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-warning position-relative px-3" data-bs-toggle="modal" data-bs-target="#cartModal" id="cartBtn">
                    <i class="bi bi-cart3 fs-5"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cartCount">
                        0
                    </span>
                </button>
                <button class="btn btn-warning d-none d-md-inline-block fw-bold" onclick="getUserLocation()">
                    <i class="bi bi-cursor-fill me-1"></i> Locate Me
                </button>
            </div>
        </div>
    </nav>

    <!-- App Main Container -->
    <div class="container-fluid px-lg-5 py-4">
        
        <!-- Welcome Toast / Notice -->
        <div id="noticeBanner" class="alert alert-warning alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4 p-4 d-flex align-items-start" role="alert">
            <span class="fs-1 me-3">🍔</span>
            <div>
                <h5 class="alert-heading fw-bold">Hungry for Street Burgers?</h5>
                <p class="mb-0 text-dark opacity-90">We've identified awesome local burger stalls ("Ramly" style, charcoal grilled, and smashed patties) around your area. Click any stall card to view their menu, location, and put in an order!</p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <div class="row g-4">
            
            <!-- Left Side: Interactive Map -->
            <div class="col-lg-7 order-lg-2">
                <div id="map-container">
                    <!-- Loading overlay -->
                    <div id="mapLoader" class="position-absolute w-100 h-100 bg-white bg-opacity-75 d-flex flex-column justify-content-center align-items-center" style="z-index: 1000; transition: opacity 0.5s;">
                        <div class="spinner-border text-warning" style="width: 3rem; height: 3rem;" role="status"></div>
                        <h5 class="mt-3 fw-bold text-dark">Locating nearby burger stalls...</h5>
                        <p class="text-muted small">Please allow location access for the best accuracy.</p>
                    </div>
                    <div id="map"></div>
                </div>
            </div>

            <!-- Right Side: Search, Filters, and List -->
            <div class="col-lg-5 order-lg-1">
                <div class="list-container pe-lg-3">
                    
                    <!-- Search & Filter Controls -->
                    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="searchInput" class="form-control bg-light border-0 py-2" placeholder="Search burger type, stall name..." oninput="filterStalls()">
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-sm btn-outline-dark rounded-pill px-3 active" id="btn-all" onclick="filterType('all')">All Stalls</button>
                            <button class="btn btn-sm btn-outline-dark rounded-pill px-3" id="btn-ramly" onclick="filterType('Ramly Style')">🍔 Ramly Style</button>
                            <button class="btn btn-sm btn-outline-dark rounded-pill px-3" id="btn-smashed" onclick="filterType('Smashed Beef')">🔥 Smashed Beef</button>
                            <button class="btn btn-sm btn-outline-dark rounded-pill px-3" id="btn-charcoal" onclick="filterType('Charcoal Grill')">🪵 Charcoal Grill</button>
                        </div>
                    </div>

                    <!-- Custom Location Info Banner -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fw-semibold small" id="stallCount">Finding stalls...</span>
                        <span class="badge bg-dark rounded-pill py-1.5 px-3" id="currentLocationLabel">
                            <i class="bi bi-geo-alt-fill text-warning me-1"></i> Cyberjaya, Selangor
                        </span>
                    </div>

                    <!-- List of Stalls -->
                    <div id="stallList" class="d-flex flex-column gap-3">
                        <!-- Dynamic content will be injected here -->
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- Burger Menu Modal -->
    <div class="modal fade" id="menuModal" tabindex="-1" aria-labelledby="menuModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-0 bg-dark text-white p-4 align-items-center">
                    <div>
                        <span class="badge bg-warning text-dark mb-1" id="modalStallType">Type</span>
                        <h4 class="modal-title fw-bold" id="menuModalLabel">Stall Menu</h4>
                        <div class="d-flex gap-2 align-items-center mt-1">
                            <small class="text-warning"><i class="bi bi-star-fill"></i> <span id="modalStallRating">4.8</span></small>
                            <small class="text-white-50">|</small>
                            <small class="text-white-50"><i class="bi bi-geo-alt"></i> <span id="modalStallDistance">0.5 km away</span></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <h5 class="fw-bold mb-3"><i class="bi bi-journal-text me-2 text-warning"></i>Signature Burgers</h5>
                    <div class="row g-3 mb-4" id="modalSignatureMenu">
                        <!-- Signature items dynamic inject -->
                    </div>

                    <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2 text-warning"></i>Drinks & Sides</h5>
                    <div class="row g-3" id="modalSidesMenu">
                        <!-- Sides/Drinks items dynamic inject -->
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted d-block small">Selected Stall</span>
                        <strong class="text-dark" id="modalStallFooterName">Stall Name</strong>
                    </div>
                    <button type="button" class="btn btn-warning px-4 py-2 fw-bold rounded-3" data-bs-toggle="modal" data-bs-target="#cartModal">
                        Go to Checkout <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Burger Customization Modal -->
    <div class="modal fade" id="customizeModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-0 bg-light p-4">
                    <h5 class="modal-title fw-bold" id="customiseTitle">Customize Burger</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="menu-item-img" id="customiseEmoji">🍔</div>
                        <div>
                            <h5 class="fw-bold mb-1" id="customiseName">Burger Name</h5>
                            <p class="text-muted small mb-0" id="customiseDesc">Burger Description</p>
                            <span class="text-success fw-bold" id="customisePrice">RM 0.00</span>
                        </div>
                    </div>

                    <form id="customiseForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Doneness / Preparation style</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="prepStyle" id="prep1" value="Classic Stall Style (Wrapped in Egg)" checked>
                                <label class="form-check-input-label" for="prep1">Classic Stall Style (Wrapped in Egg)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="prepStyle" id="prep2" value="No Egg Wrap (Naked Patty)">
                                <label class="form-check-input-label" for="prep2">No Egg Wrap (Naked Patty)</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Extra Add-ons (Optional)</label>
                            <div class="form-check d-flex justify-content-between align-items-center py-1">
                                <div>
                                    <input class="form-check-input" type="checkbox" id="addonCheese" value="1.50">
                                    <label class="form-check-label" for="addonCheese">Extra Sliced Cheddar Cheese</label>
                                </div>
                                <span class="text-muted small">+RM1.50</span>
                            </div>
                            <div class="form-check d-flex justify-content-between align-items-center py-1">
                                <div>
                                    <input class="form-check-input" type="checkbox" id="addonEgg" value="1.00">
                                    <label class="form-check-label" for="addonEgg">Extra Fried Egg</label>
                                </div>
                                <span class="text-muted small">+RM1.00</span>
                            </div>
                            <div class="form-check d-flex justify-content-between align-items-center py-1">
                                <div>
                                    <input class="form-check-input" type="checkbox" id="addonSauce" value="0.50">
                                    <label class="form-check-label" for="addonSauce">Extra Black Pepper Sauce ("Banjir")</label>
                                </div>
                                <span class="text-muted small">+RM0.50</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Special Remarks</label>
                            <textarea class="form-control" id="specialRemarks" rows="2" placeholder="e.g., No spicy sauce, extra mayo please!"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-4 bg-light d-flex justify-content-between">
                    <div>
                        <span class="text-muted d-block small">Total Item Price</span>
                        <strong class="text-success fs-5" id="customiseTotalDisplay">RM 0.00</strong>
                    </div>
                    <button type="button" class="btn btn-warning px-4 fw-bold" onclick="addItemToCart()">Add to Basket</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Shopping Cart / Checkout Modal -->
    <div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-0 bg-dark text-white p-4">
                    <h5 class="modal-title fw-bold" id="cartModalLabel"><i class="bi bi-cart3 me-2 text-warning"></i>Your Basket</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="cartContent">
                    <!-- Dynamic cart items list -->
                </div>
                <div class="modal-footer border-0 p-4 bg-light d-flex flex-column gap-3">
                    <div class="w-100 d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Total Bill:</span>
                        <span class="fs-4 fw-bold text-success" id="cartTotalDisplay">RM 0.00</span>
                    </div>
                    <div class="w-100 d-flex gap-2">
                        <button class="btn btn-outline-secondary w-50" data-bs-dismiss="modal">Keep Browsing</button>
                        <button class="btn btn-warning w-50 fw-bold" id="checkoutBtn" onclick="placeOrder()">Place Order</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Dialog Modal (No alerts!) -->
    <div class="modal fade" id="customAlertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-body text-center p-4">
                    <div id="alertIcon" class="text-success display-4 mb-3">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h5 class="fw-bold" id="alertTitle">Success</h5>
                    <p class="text-muted mb-3" id="alertMsg">Message goes here.</p>
                    <button class="btn btn-dark w-100 rounded-3" data-bs-dismiss="modal">Got it</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-time Order Tracker Modal -->
    <div class="modal fade" id="trackerModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-0 bg-dark text-white p-4">
                    <h5 class="modal-title fw-bold"><i class="bi bi-activity text-warning me-2 animate-pulse"></i>Grill Status Tracker</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="closeTrackerBtn" disabled></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="spinner-grow text-warning mb-3" style="width: 3rem; height: 3rem;" role="status" id="trackerStatusSpinner"></div>
                        <h4 class="fw-bold" id="trackerStatusHeader">Grilling your burger...</h4>
                        <p class="text-muted" id="trackerSubText">The stall master is wrapping your patty in egg!</p>
                    </div>

                    <!-- Step progress timeline -->
                    <div class="timeline mt-4">
                        <div class="timeline-item completed" id="step-received">
                            <h6 class="fw-bold mb-1">Order Received</h6>
                            <p class="text-muted small mb-0">Stall master acknowledged your burger order.</p>
                        </div>
                        <div class="timeline-item active" id="step-preparing">
                            <h6 class="fw-bold mb-1">Grilling & Prepping</h6>
                            <p class="text-muted small mb-0">Patties are sizzling on the flat top grill.</p>
                        </div>
                        <div class="timeline-item" id="step-ready">
                            <h6 class="fw-bold mb-1">Packed & Ready</h6>
                            <p class="text-muted small mb-0">Burgers are hot, wrapped, and bagged for pickup.</p>
                        </div>
                    </div>

                    <div class="card bg-warning bg-opacity-10 border-0 p-3 rounded-3 mt-4">
                        <div class="d-flex align-items-center">
                            <span class="fs-2 me-3">🚴‍♂️</span>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Stall Location</h6>
                                <span class="text-muted small" id="trackerStallLocation">Stall Location Address</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 bg-light">
                    <button type="button" class="btn btn-dark w-100 fw-bold rounded-3" id="doneTrackerBtn" data-bs-dismiss="modal" disabled>Awesome, Let's Eat!</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Leaflet JS Map Script -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- App Business Logic Script -->
    <script>
        // Global App State
        let map;
        let userMarker;
        let markersGroup;
        let selectedStall = null;
        let currentFilter = 'all';
        let searchQuery = '';
        let cart = [];
        let activeTempItem = null;

        // Custom modals hooks
        let menuModal;
        let customizeModal;
        let cartModal;
        let customAlertModal;
        let trackerModal;

        // User Geolocation (Fallback: Cyberjaya, Malaysia coordinates)
        let userLat = 2.9213; 
        let userLng = 101.6559;
        let isDefaultLocation = true;

        // Base Burger Stall Database (These will be dynamically calculated relative to the user's live coordinates)
        const baseStallDatabase = [
            {
                id: 1,
                name: "Brother Bob's Banjir Burger",
                type: "Ramly Style",
                rating: 4.8,
                reviews: 142,
                specialty: "Sausage Special, Egg Wrapped Beef",
                address: "Jalan Teknokrat 5, Corner Food Alley",
                offsetLat: 0.0035,
                offsetLng: -0.0042,
                menu: {
                    signature: [
                        { name: "Special Beef Burger (Double)", price: 9.50, desc: "Classic street burger, two patties wrapped meticulously inside a fried egg with cheese and black pepper drizzle.", emoji: "🍔" },
                        { name: "Sausage Banjir Special", price: 7.50, desc: "Grilled sausage sliced up with tons of cheese sauce, sweet chili, and cabbage.", emoji: "🌭" }
                    ],
                    sides: [
                        { name: "Chili Cheese Fries", price: 6.00, desc: "Fries topped with warm cheese sauce and meat seasoning.", emoji: "🍟" },
                        { name: "Teh Tarik Ice", price: 3.50, desc: "Classic sweetened milk tea.", emoji: "🥤" }
                    ]
                }
            },
            {
                id: 2,
                name: "The Sizzle Smash Pit",
                type: "Smashed Beef",
                rating: 4.9,
                reviews: 89,
                specialty: "Caramelized Onion Triple Smash",
                address: "Lebuh Raya Cyber, Food Truck Zone",
                offsetLat: -0.0051,
                offsetLng: 0.0038,
                menu: {
                    signature: [
                        { name: "Ultimate Double Smash", price: 13.50, desc: "Two ultra-crisp smashed beef patties, house butter, high-melt cheddar, signature relish.", emoji: "🍔" },
                        { name: "Triple Sizzler", price: 17.00, desc: "For the big appetites. Three crispy smashed beef patties, custom Sizzle Sauce.", emoji: "🍔" }
                    ],
                    sides: [
                        { name: "Onion Ring Basket", price: 5.50, desc: "Battered and extra-crispy fried sweet onion rings.", emoji: "🧅" },
                        { name: "Fresh Lemonade Splash", price: 4.00, desc: "Cold, tangy, refreshing lemonade.", emoji: "🥤" }
                    ]
                }
            },
            {
                id: 3,
                name: "Kak Nora's Charcoal Grills",
                type: "Charcoal Grill",
                rating: 4.7,
                reviews: 215,
                specialty: "Thick Charcoal Chicken, Gourmet Feel",
                address: "Persiaran Multimedia, Beside Metro Station",
                offsetLat: 0.0062,
                offsetLng: 0.0051,
                menu: {
                    signature: [
                        { name: "Charcoal Flame-Grilled Chicken", price: 11.50, desc: "Thick hand-pressed chicken patty infused with real hickory charcoal wood smoke.", emoji: "🍗" },
                        { name: "Nora's Smoked Beef Gourmet", price: 12.50, desc: "Quarter pound patty slow grilled over genuine charcoal embers, special garlic mayo.", emoji: "🍔" }
                    ],
                    sides: [
                        { name: "Sweet Potato Wedges", price: 6.50, desc: "Crispy skin-on sweet potatoes with savory spices.", emoji: "🍠" },
                        { name: "Iced Milo Dinosaur", price: 4.50, desc: "Chocolate malt drink with an extra mountain of cocoa powder on top.", emoji: "🥤" }
                    ]
                }
            },
            {
                id: 4,
                name: "Ramly Express Hub",
                type: "Ramly Style",
                rating: 4.5,
                reviews: 320,
                specialty: "Super Speed Burger Special",
                address: "Jalan Persiaran APEC, In Front of Convenience Store",
                offsetLat: -0.0021,
                offsetLng: -0.0068,
                menu: {
                    signature: [
                        { name: "Lamb Burger Special", price: 8.50, desc: "Savory street-grilled lamb patty, thoroughly spiced, wrapped with egg, cabbage, and extra mayo.", emoji: "🍔" },
                        { name: "Slinging Fish Burger", price: 7.00, desc: "Golden fried fish patty with local sweet chili relish and warm burger buns.", emoji: "🐟" }
                    ],
                    sides: [
                        { name: "Nugget Box (6pcs)", price: 5.00, desc: "Deep-fried premium street chicken nuggets with chili dipping sauce.", emoji: "🍗" },
                        { name: "Iced Sirap Bandung", price: 3.00, desc: "Sweet rose syrup milk over ice.", emoji: "🥛" }
                    ]
                }
            },
            {
                id: 5,
                name: "Smokey Joe's Street Truck",
                type: "Charcoal Grill",
                rating: 4.6,
                reviews: 64,
                specialty: "Hickory Smoke Beef Burgers",
                address: "Jalan Cyberia 3, Corner Lot",
                offsetLat: 0.0012,
                offsetLng: 0.0075,
                menu: {
                    signature: [
                        { name: "BBQ Smoked Bacon-Beef Combo", price: 14.00, desc: "Beef patty combined with crispy beef bacon slices, dripping with rich smoky glaze.", emoji: "🥓" },
                        { name: "Hickory Pulled Beef Bun", price: 15.00, desc: "Slow-roasted pulled beef shredded into our sweet glaze in a toasted brioche bun.", emoji: "🍔" }
                    ],
                    sides: [
                        { name: "Cheesy Potato Wedges", price: 6.00, desc: "Gently spiced wedges completely covered in liquid cheese sauce.", emoji: "🍟" },
                        { name: "Iced Lemon Tea", price: 3.00, desc: "Sweet and chilled lemon brew.", emoji: "🥤" }
                    ]
                }
            }
        ];

        let dynamicallyPositionedStalls = [];

        // Application Initialization on Window Load
        window.onload = function() {
            // Initialize Bootstrap Modals
            menuModal = new bootstrap.Modal(document.getElementById('menuModal'));
            customizeModal = new bootstrap.Modal(document.getElementById('customizeModal'));
            cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
            customAlertModal = new bootstrap.Modal(document.getElementById('customAlertModal'));
            trackerModal = new bootstrap.Modal(document.getElementById('trackerModal'));

            // Set default location label
            document.getElementById('currentLocationLabel').innerHTML = `<i class="bi bi-geo-alt-fill text-warning me-1"></i> Cyberjaya, Selangor (Estimated Location)`;

            // Attempt to get user geolocation immediately
            getUserLocation();
        };

        // Get User Coordinates via Browser API
        function getUserLocation() {
            document.getElementById('mapLoader').style.opacity = '1';
            document.getElementById('mapLoader').style.pointerEvents = 'all';

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        userLat = position.coords.latitude;
                        userLng = position.coords.longitude;
                        isDefaultLocation = false;
                        document.getElementById('currentLocationLabel').innerHTML = `<i class="bi bi-geo-alt-fill text-warning me-1"></i> My Location (Live)`;
                        initializeMapAndStalls();
                    },
                    (error) => {
                        console.warn("Geolocation permission denied or failed, using default (Cyberjaya).", error);
                        // Default to Cyberjaya coordinates
                        userLat = 2.9213;
                        userLng = 101.6559;
                        isDefaultLocation = true;
                        document.getElementById('currentLocationLabel').innerHTML = `<i class="bi bi-geo-alt-fill text-warning me-1"></i> Cyberjaya, Selangor (Default)`;
                        initializeMapAndStalls();
                    },
                    { timeout: 8000 }
                );
            } else {
                initializeMapAndStalls();
            }
        }

        // Initialize Map & Stalls Position
        function initializeMapAndStalls() {
            // Compute real coordinates for stalls relative to User Center
            dynamicallyPositionedStalls = baseStallDatabase.map(stall => {
                const computedLat = userLat + stall.offsetLat;
                const computedLng = userLng + stall.offsetLng;
                const distance = calculateDistance(userLat, userLng, computedLat, computedLng);
                return {
                    ...stall,
                    lat: computedLat,
                    lng: computedLng,
                    distance: distance // in km
                };
            });

            // Sort dynamically positioned stalls by proximity
            dynamicallyPositionedStalls.sort((a, b) => a.distance - b.distance);

            // Set up or Refresh Map
            if (!map) {
                map = L.map('map').setView([userLat, userLng], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                markersGroup = L.layerGroup().addTo(map);
            } else {
                map.setView([userLat, userLng], 15);
                markersGroup.clearLayers();
            }

            // Draw User Marker
            const userIcon = L.divIcon({
                html: '<div class="custom-marker-icon user-marker"><i class="bi bi-person-fill"></i></div>',
                className: '',
                iconSize: [36, 36],
                iconAnchor: [18, 18]
            });
            userMarker = L.marker([userLat, userLng], { icon: userIcon })
                .addTo(markersGroup)
                .bindPopup('<b>Your Current Position</b><br>Searching for sizzling hot burgers around you!')
                .openPopup();

            // Draw Stall Markers
            dynamicallyPositionedStalls.forEach(stall => {
                const iconHtml = `<div class="custom-marker-icon bg-warning" id="map-marker-${stall.id}"><i class="bi bi-fire"></i></div>`;
                const stallIcon = L.divIcon({
                    html: iconHtml,
                    className: '',
                    iconSize: [36, 36],
                    iconAnchor: [18, 18]
                });

                const marker = L.marker([stall.lat, stall.lng], { icon: stallIcon })
                    .addTo(markersGroup)
                    .bindPopup(`
                        <div class="p-1">
                            <span class="badge bg-warning text-dark mb-1">${stall.type}</span>
                            <h6 class="fw-bold mb-1">${stall.name}</h6>
                            <p class="text-muted small mb-1">${stall.address}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-dark fw-bold text-decoration-none" style="font-size:12px;"><i class="bi bi-star-fill text-warning"></i> ${stall.rating} (${stall.reviews} reviews)</span>
                                <button onclick="selectStall(${stall.id}, true)" class="btn btn-xs btn-dark py-1 px-2 rounded small" style="font-size:11px;">View Menu</button>
                            </div>
                        </div>
                    `);
                
                // Track marker reference on stall object
                stall.marker = marker;
            });

            // Populate the UI List
            renderStallsList();

            // Hide Loader Spinner
            setTimeout(() => {
                document.getElementById('mapLoader').style.opacity = '0';
                document.getElementById('mapLoader').style.pointerEvents = 'none';
            }, 600);
        }

        // Calculate Distance using Haversine formula
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Radius of earth in km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            const d = R * c;
            return d.toFixed(2);
        }

        // Render Stalls into Sidebar List
        function renderStallsList() {
            const listContainer = document.getElementById('stallList');
            listContainer.innerHTML = '';

            const filtered = dynamicallyPositionedStalls.filter(stall => {
                const matchesSearch = stall.name.toLowerCase().includes(searchQuery.toLowerCase()) || 
                                      stall.specialty.toLowerCase().includes(searchQuery.toLowerCase());
                const matchesType = currentFilter === 'all' || stall.type === currentFilter;
                return matchesSearch && matchesType;
            });

            document.getElementById('stallCount').innerText = `${filtered.length} Burger Stalls Nearby`;

            if (filtered.length === 0) {
                listContainer.innerHTML = `
                    <div class="text-center py-5">
                        <span class="fs-1">🔍</span>
                        <h5 class="fw-bold mt-2">No matching stalls found</h5>
                        <p class="text-muted small">Try searching or choosing another category above.</p>
                    </div>
                `;
                return;
            }

            filtered.forEach(stall => {
                const isActive = selectedStall && selectedStall.id === stall.id;
                const card = document.createElement('div');
                card.className = `card border-2 shadow-sm stall-card p-3 ${isActive ? 'active-stall' : 'bg-white'}`;
                card.setAttribute('id', `stall-card-${stall.id}`);
                card.setAttribute('onclick', `selectStall(${stall.id}, false)`);
                
                card.innerHTML = `
                    <div class="row align-items-center g-2">
                        <div class="col-8">
                            <span class="badge badge-amber mb-1">${stall.type}</span>
                            <h5 class="fw-bold text-dark mb-1">${stall.name}</h5>
                            <p class="text-muted small mb-1"><i class="bi bi-geo-alt-fill text-warning"></i> ${stall.address}</p>
                            <p class="text-muted small mb-0"><i class="bi bi-tag-fill me-1"></i> ${stall.specialty}</p>
                        </div>
                        <div class="col-4 text-end">
                            <span class="badge bg-dark rounded-pill py-1.5 px-2.5 small d-inline-block mb-1">${stall.distance} km away</span>
                            <div class="text-warning mb-2"><i class="bi bi-star-fill"></i> <strong class="text-dark">${stall.rating}</strong> <span class="text-muted small">(${stall.reviews})</span></div>
                            <button class="btn btn-warning btn-sm fw-bold w-100 rounded-3" onclick="openStallMenu(event, ${stall.id})">Order Here</button>
                        </div>
                    </div>
                `;
                listContainer.appendChild(card);
            });
        }

        // Selection Actions for Stalls
        function selectStall(id, fromMap = false) {
            const stall = dynamicallyPositionedStalls.find(s => s.id === id);
            if (!stall) return;

            selectedStall = stall;

            // Update UI list highlight
            document.querySelectorAll('.stall-card').forEach(card => card.classList.remove('active-stall'));
            const activeCard = document.getElementById(`stall-card-${id}`);
            if (activeCard) {
                activeCard.classList.add('active-stall');
                if (!fromMap) {
                    activeCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }

            // Animate map transition to stall position
            map.setView([stall.lat, stall.lng], 16);
            stall.marker.openPopup();

            // Animate marker scale to draw visual interest
            const markerDiv = document.getElementById(`map-marker-${id}`);
            if (markerDiv) {
                markerDiv.style.transform = 'scale(1.3)';
                setTimeout(() => {
                    markerDiv.style.transform = 'scale(1)';
                }, 500);
            }
        }

        // Open Menu Details Modal
        function openStallMenu(event, stallId) {
            if (event) event.stopPropagation(); // Prevents triggering selectStall double action
            
            const stall = dynamicallyPositionedStalls.find(s => s.id === stallId);
            if (!stall) return;

            selectedStall = stall;
            selectStall(stallId, false);

            document.getElementById('modalStallType').innerText = stall.type;
            document.getElementById('modalStallRating').innerText = stall.rating;
            document.getElementById('modalStallDistance').innerText = `${stall.distance} km away`;
            document.getElementById('menuModalLabel').innerText = `${stall.name} Menu`;
            document.getElementById('modalStallFooterName').innerText = stall.name;

            // Generate Signatures Menu Cards
            const signatureContainer = document.getElementById('modalSignatureMenu');
            signatureContainer.innerHTML = '';
            stall.menu.signature.forEach((item, index) => {
                signatureContainer.innerHTML += `
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm p-3 rounded-4 bg-white">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">${item.name}</h6>
                                    <p class="text-muted small mb-2">${item.desc}</p>
                                    <strong class="text-success fs-6">RM ${item.price.toFixed(2)}</strong>
                                </div>
                                <div class="menu-item-img flex-shrink-0">${item.emoji}</div>
                            </div>
                            <div class="mt-3 text-end">
                                <button class="btn btn-warning btn-sm fw-bold px-3 py-1.5 rounded-3" onclick="openCustomizeModal('${item.name}', ${item.price}, '${item.desc}', '${item.emoji}')">
                                    <i class="bi bi-plus-lg"></i> Select
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            // Generate Sides Menu Cards
            const sidesContainer = document.getElementById('modalSidesMenu');
            sidesContainer.innerHTML = '';
            stall.menu.sides.forEach((item, index) => {
                sidesContainer.innerHTML += `
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm p-3 rounded-4 bg-white">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">${item.name}</h6>
                                    <p class="text-muted small mb-2">${item.desc}</p>
                                    <strong class="text-success fs-6">RM ${item.price.toFixed(2)}</strong>
                                </div>
                                <div class="menu-item-img flex-shrink-0">${item.emoji}</div>
                            </div>
                            <div class="mt-3 text-end">
                                <button class="btn btn-warning btn-sm fw-bold px-3 py-1.5 rounded-3" onclick="openCustomizeModal('${item.name}', ${item.price}, '${item.desc}', '${item.emoji}')">
                                    <i class="bi bi-plus-lg"></i> Select
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            menuModal.show();
        }

        // Open Customize Options Modal
        function openCustomizeModal(name, basePrice, desc, emoji) {
            activeTempItem = {
                name: name,
                basePrice: basePrice,
                currentTotal: basePrice,
                desc: desc,
                emoji: emoji,
                addons: []
            };

            // Reset modal options state
            document.getElementById('customiseForm').reset();
            
            document.getElementById('customiseTitle').innerText = `Assemble Your Burger`;
            document.getElementById('customiseName').innerText = name;
            document.getElementById('customiseDesc').innerText = desc;
            document.getElementById('customiseEmoji').innerText = emoji;
            document.getElementById('customisePrice').innerText = `Base: RM ${basePrice.toFixed(2)}`;
            document.getElementById('customiseTotalDisplay').innerText = `RM ${basePrice.toFixed(2)}`;
            document.getElementById('specialRemarks').value = '';

            // Setup real-time price updates on form changes
            const addonCheckboxes = ['addonCheese', 'addonEgg', 'addonSauce'];
            addonCheckboxes.forEach(id => {
                document.getElementById(id).onchange = recalculateCustomizePrice;
            });

            customizeModal.show();
        }

        // Recalculate customizable total real-time
        function recalculateCustomizePrice() {
            if (!activeTempItem) return;
            let sum = activeTempItem.basePrice;
            activeTempItem.addons = [];

            if (document.getElementById('addonCheese').checked) {
                sum += parseFloat(document.getElementById('addonCheese').value);
                activeTempItem.addons.push("Extra Cheddar Cheese");
            }
            if (document.getElementById('addonEgg').checked) {
                sum += parseFloat(document.getElementById('addonEgg').value);
                activeTempItem.addons.push("Extra Fried Egg");
            }
            if (document.getElementById('addonSauce').checked) {
                sum += parseFloat(document.getElementById('addonSauce').value);
                activeTempItem.addons.push("Banjir Pepper Sauce");
            }

            activeTempItem.currentTotal = sum;
            document.getElementById('customiseTotalDisplay').innerText = `RM ${sum.toFixed(2)}`;
        }

        // Add Customized Item to Cart Array
        function addItemToCart() {
            if (!activeTempItem || !selectedStall) return;

            const prepStyle = document.querySelector('input[name="prepStyle"]:checked').value;
            const remarks = document.getElementById('specialRemarks').value;

            const finalCartItem = {
                id: Date.now(), // unique ID to distinguish matching names with separate configs
                stallId: selectedStall.id,
                stallName: selectedStall.name,
                name: activeTempItem.name,
                price: activeTempItem.currentTotal,
                emoji: activeTempItem.emoji,
                prepStyle: prepStyle,
                addons: [...activeTempItem.addons],
                remarks: remarks
            };

            // Limit cart items to single stall to emulate delivery parameters
            if (cart.length > 0 && cart[0].stallId !== selectedStall.id) {
                // Wipe previous cart items if ordering from a different stall
                cart = [];
            }

            cart.push(finalCartItem);
            updateCartCounter();

            customizeModal.hide();
            menuModal.hide();

            showCustomAlert(
                "bi-bag-check-fill text-warning",
                "Added to Basket",
                `Successfully added ${finalCartItem.name} to your order basket from ${selectedStall.name}!`
            );
        }

        // Update Nav Cart badge counters
        function updateCartCounter() {
            const counter = document.getElementById('cartCount');
            counter.innerText = cart.length;
            if (cart.length > 0) {
                counter.classList.remove('d-none');
            } else {
                counter.classList.add('d-none');
            }

            renderCartModalContent();
        }

        // Render Current Basket List
        function renderCartModalContent() {
            const container = document.getElementById('cartContent');
            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="bi bi-cart-x display-1 text-muted"></i>
                        <h5 class="fw-bold mt-3">Your basket is empty</h5>
                        <p class="text-muted small">Select a nearby burger stall to start adding delicious burgers!</p>
                    </div>
                `;
                document.getElementById('cartTotalDisplay').innerText = "RM 0.00";
                document.getElementById('checkoutBtn').disabled = true;
                return;
            }

            let totalBill = 0;
            let listHtml = `<div class="mb-3 border-bottom pb-2">
                                <span class="badge bg-warning text-dark">Ordering From</span>
                                <h6 class="fw-bold mt-1 mb-0"><i class="bi bi-shop"></i> ${cart[0].stallName}</h6>
                            </div>`;

            cart.forEach((item) => {
                totalBill += item.price;
                const addonsLabel = item.addons.length > 0 ? `+ ${item.addons.join(', ')}` : 'Classic Style';
                listHtml += `
                    <div class="d-flex justify-content-between align-items-start mb-3 border-bottom pb-3">
                        <div class="d-flex gap-3">
                            <div class="fs-2">${item.emoji}</div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">${item.name}</h6>
                                <p class="text-muted small mb-1">${item.prepStyle}</p>
                                <p class="text-warning small mb-0 fw-semibold">${addonsLabel}</p>
                                ${item.remarks ? `<p class="text-muted small mb-0 font-italic">"${item.remarks}"</p>` : ''}
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold text-success d-block">RM ${item.price.toFixed(2)}</span>
                            <button class="btn btn-link text-danger btn-sm p-0 text-decoration-none mt-1" onclick="removeCartItem(${item.id})">
                                <i class="bi bi-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = listHtml;
            document.getElementById('cartTotalDisplay').innerText = `RM ${totalBill.toFixed(2)}`;
            document.getElementById('checkoutBtn').disabled = false;
        }

        // Remove single item from cart array
        function removeCartItem(itemId) {
            cart = cart.filter(item => item.id !== itemId);
            updateCartCounter();
        }

        // Action: Place Mock Order & Trigger Progress Tracker
        function placeOrder() {
            if (cart.length === 0) return;

            const stall = dynamicallyPositionedStalls.find(s => s.id === cart[0].stallId);
            cartModal.hide();

            // Set Location display inside Tracker Modal
            document.getElementById('trackerStallLocation').innerText = `${stall.name} — Located at ${stall.address}`;

            // Initialize Progress Steps Animation
            trackerModal.show();
            startTrackerAnimation();
        }

        // Simulate preparation times & update UI step tracker
        function startTrackerAnimation() {
            const steps = [
                { id: 'step-received', subText: 'Stall master acknowledged your burger order.' },
                { id: 'step-preparing', subText: 'Patties are sizzling on the hot flat top grill!' },
                { id: 'step-ready', subText: 'Wrapped tightly in customized greasepaper. Ready!' }
            ];

            // Setup default buttons
            document.getElementById('closeTrackerBtn').disabled = true;
            document.getElementById('doneTrackerBtn').disabled = true;

            // Step 1 Completed immediately
            setStepState('step-received', 'completed');
            setStepState('step-preparing', 'active');
            setStepState('step-ready', 'pending');
            updateTrackerHeader("Sizzling Burger Grills...", "The cook is slicing fresh onions and buttering buns.");

            // Progress to Step 2 (Grilling)
            setTimeout(() => {
                setStepState('step-received', 'completed');
                setStepState('step-preparing', 'completed');
                setStepState('step-ready', 'active');
                updateTrackerHeader("Wrapping in egg envelope...", "Adding spicy sweet sauce and folding into toasted buns!");
            }, 5000);

            // Ready for pick-up / completion
            setTimeout(() => {
                setStepState('step-ready', 'completed');
                updateTrackerHeader("Burgers are Ready! 🎉", "Your sizzling meal is hot and waiting at the counter.");
                
                // Stop general loading spinners
                document.getElementById('trackerStatusSpinner').classList.add('d-none');
                
                // Allow user to exit tracker
                document.getElementById('closeTrackerBtn').disabled = false;
                document.getElementById('doneTrackerBtn').disabled = false;

                // Clear Shopping Cart on completion
                cart = [];
                updateCartCounter();
            }, 10000);
        }

        // Utility: Update visual tracker elements
        function setStepState(stepId, state) {
            const el = document.getElementById(stepId);
            if (!el) return;

            el.classList.remove('completed', 'active');
            if (state === 'completed') {
                el.classList.add('completed');
            } else if (state === 'active') {
                el.classList.add('active');
            }
        }

        function updateTrackerHeader(headerText, subText) {
            document.getElementById('trackerStatusHeader').innerText = headerText;
            document.getElementById('trackerSubText').innerText = subText;
        }

        // Show generic popup message custom modal
        function showCustomAlert(iconClass, title, msg) {
            document.getElementById('alertIcon').className = `display-4 mb-3 ${iconClass}`;
            document.getElementById('alertTitle').innerText = title;
            document.getElementById('alertMsg').innerText = msg;
            customAlertModal.show();
        }

        // Filtering Logic for stall list
        function filterType(type) {
            currentFilter = type;
            
            // Toggle active classes on filter button pills
            const btnMap = {
                'all': 'btn-all',
                'Ramly Style': 'btn-ramly',
                'Smashed Beef': 'btn-smashed',
                'Charcoal Grill': 'btn-charcoal'
            };

            Object.values(btnMap).forEach(btnId => {
                document.getElementById(btnId).classList.remove('active');
            });
            document.getElementById(btnMap[type]).classList.add('active');

            renderStallsList();
            updateMapMarkersVisibility();
        }

        // Live text input filtering
        function filterStalls() {
            searchQuery = document.getElementById('searchInput').value;
            renderStallsList();
            updateMapMarkersVisibility();
        }

        // Update markers display status based on active filters
        function updateMapMarkersVisibility() {
            dynamicallyPositionedStalls.forEach(stall => {
                const matchesSearch = stall.name.toLowerCase().includes(searchQuery.toLowerCase()) || 
                                      stall.specialty.toLowerCase().includes(searchQuery.toLowerCase());
                const matchesType = currentFilter === 'all' || stall.type === currentFilter;

                if (matchesSearch && matchesType) {
                    if (!map.hasLayer(stall.marker)) {
                        stall.marker.addTo(markersGroup);
                    }
                } else {
                    if (map.hasLayer(stall.marker)) {
                        map.removeLayer(stall.marker);
                    }
                }
            });
        }
    </script>

</body>
</html>