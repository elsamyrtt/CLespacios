const products = [
    { id: 1, name: "Silla Ergonómica de Oficina", price: 199.99, image: "https://picsum.photos/220/60", category: "sillas", rating: 4.5 },
    { id: 2, name: "Sofá Modular para Sala", price: 599.99, image: "https://picsum.photos/220/60", category: "muebles", rating: 4.8 },
    { id: 3, name: "Cocina Integral Moderna", price: 2999.99, image: "https://picsum.photos/220/60", category: "cocinas", rating: 4.7 },
    { id: 4, name: "Mesa de Centro", price: 149.99, image: "https://picsum.photos/220/60", category: "muebles", rating: 4.2 },
    { id: 5, name: "Lámpara de Pie", price: 79.99, image: "https://picsum.photos/220/60", category: "iluminacion", rating: 4.0 },
    { id: 6, name: "Armario Empotrado", price: 899.99, image: "https://picsum.photos/220/60", category: "muebles", rating: 4.6 },
];

let cart = [];
let filteredProducts = [...products];
let currentIndex = 0;

function initializeShop() {
    const productGrid = document.getElementById('product-grid');
    const categoryList = document.querySelector('.category-list');
    const priceRange = document.getElementById('price-range');
    const minPriceInput = document.getElementById('min-price');
    const maxPriceInput = document.getElementById('max-price');
    const applyFiltersBtn = document.querySelector('.apply-filters-btn');
    const sortSelect = document.querySelector('.sort-options select');
    const cartIcon = document.getElementById('cart-icon');

    if (cartIcon) {
        cartIcon.addEventListener('click', showCart);
    }

    displayProducts(products);

    const topRatedProducts = products.sort((a, b) => b.rating - a.rating).slice(0, 10);
    displayProductsInPopulateIndex(topRatedProducts);


    if (categoryList) {
        categoryList.addEventListener('click', handleCategoryFilter);
    }

    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', applyPriceFilter);
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', sortProducts);
    }
    initializeCarousel();
}

function initializeCarousel() {
    const leftBtn = document.querySelector('.left-btn');
    const rightBtn = document.querySelector('.right-btn');
    const productGrid = document.getElementById('product-grid-populate-index');

    if (leftBtn && rightBtn) {
        leftBtn.addEventListener('click', () => {
            if (currentIndex > 0) {
                currentIndex--;
                updateCarousel();
            }
        });

        rightBtn.addEventListener('click', () => {
            const maxIndex = Math.ceil(productGrid.children.length / 2) - 1;
            if (currentIndex < maxIndex) {
                currentIndex++;
                updateCarousel();
            }
        });

        updateCarousel();
    }
}
function updateCarousel() {
    const productGrid = document.getElementById('product-grid-populate-index');
    const productCard = productGrid.querySelector('.product-card');

    if (productCard) {
        const productCardWidth = productCard.offsetWidth; // Ancho de la tarjeta
        const productCardMargin = parseInt(window.getComputedStyle(productCard).marginRight); // Margen derecho de la tarjeta
        const offset = currentIndex * (productCardWidth + productCardMargin) * 2; // Desplazamiento incluyendo márgenes

        productGrid.style.transform = `translateX(-${offset}px)`;
        productGrid.style.transition = 'transform 0.5s ease';
    }
}

function handleCategoryFilter(e) {
    e.preventDefault();
    if (e.target.tagName === 'A') {
        const category = e.target.getAttribute('category');
        filteredProducts = category === 'all' ? products : products.filter(product => product.category === category);
        displayProducts(filteredProducts);
    }
}

function applyPriceFilter() {
    const minPriceInput = document.getElementById('min-price');
    const maxPriceInput = document.getElementById('max-price');
    const minPrice = parseFloat(minPriceInput.value) || 0;
    const maxPrice = parseFloat(maxPriceInput.value) || Infinity;
    filteredProducts = products.filter(product => product.price >= minPrice && product.price <= maxPrice);
    displayProducts(filteredProducts);
}

function displayProductsInPopulateIndex(productsToShow) {
    const productGridPopulateIndex = document.getElementById('product-grid-populate-index');
    if (productGridPopulateIndex) {
        productGridPopulateIndex.innerHTML = '';
        productsToShow.forEach(product => {
            const productCard = createProductCard(product);
            productGridPopulateIndex.appendChild(productCard);
        });
        updateCarousel();
    }
}

function sortProducts() {
    const sortValue = this.value;
    filteredProducts.sort((a, b) => {
        if (sortValue === 'price-asc') return a.price - b.price;
        if (sortValue === 'price-desc') return b.price - a.price;
        if (sortValue === 'rating') return b.rating - a.rating;
        return 0;
    });
    displayProducts(filteredProducts);
}

function displayProducts(productsToShow) {
    const productGrid = document.getElementById('product-grid');
    if (productGrid) {
        productGrid.innerHTML = '';
        productsToShow.forEach(product => {
            const productCard = createProductCard(product);
            productGrid.appendChild(productCard);
        });
    }
}

function createProductCard(product) {
    const card = document.createElement('div');
    card.className = 'product-card';
    card.innerHTML = `
        <img src="${product.image}" alt="${product.name}">
        <div class="product-info">
            <h3>${product.name}</h3>
            <p class="price">$${product.price.toFixed(2)}</p>
            <p class="rating">★★★★★ ${product.rating.toFixed(1)}</p>
            <button class="add-to-cart" data-id="${product.id}">Agregar al Carrito</button>
        </div>
    `;
    card.querySelector('.add-to-cart').addEventListener('click', () => addToCart(product));
    return card;
}
function addToCart(productId) {
    const product = products.find(p => p.id === productId);
    if (product) {
        const cartItem = cart.find(item => item.id === product.id);
        if (cartItem) {
            cartItem.quantity += 1;
        } else {
            cart.push({ ...product, quantity: 1 });
        }
        saveCartToLocalStorage();
        updateCartCount();
    }
}


function saveCartToLocalStorage() {
    localStorage.setItem('cart', JSON.stringify(cart));
}
function loadCartFromLocalStorage() {
    const cartFromStorage = localStorage.getItem('cart');
    if (cartFromStorage) {
        cart = JSON.parse(cartFromStorage);
    }
}


function updateCartCount() {
    const cartCount = document.getElementById('cart-count');
    if (cartCount) {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCount.textContent = totalItems;
    }
}

function showCart() {
    const modal = createCartModal();
    document.body.appendChild(modal);
    populateCartModal(modal);
    setupCartModalEvents(modal);
}

function populateCartModal(modal) {
    const cartItemsContainer = modal.querySelector('#cart-items');
    const cartTotal = modal.querySelector('#cart-total');
    let total = 0;

    cart.forEach(item => {
        const itemElement = document.createElement('div');
        itemElement.innerHTML = `
            <p>${item.name} - Cantidad: ${item.quantity} - $${(item.price * item.quantity).toFixed(2)}</p>
        `;
        cartItemsContainer.appendChild(itemElement);
        total += item.price * item.quantity;
    });

    cartTotal.textContent = total.toFixed(2);
    modal.style.display = 'block';
}


function createCartModal() {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Carrito de Compras</h2>
            <div id="cart-items"></div>
            <p>Total: $<span id="cart-total"></span></p>
            <button id="checkout-btn">Proceder al Pago</button>
        </div>
    `;
    return modal;
}
function showBar() {
    bar = document.getElementById("barmenu");
    bar.style.display = "block";
    exitbt = document.getElementById("CloseOpcID");
    exitbt.style.display = "block";
}
function ExitButtonBar() {
    exitbt = document.getElementById("CloseOpcID");
    exitbt.style.display = "none";
    bar = document.getElementById("barmenu");
    bar.style.display = "none";
}
function setupCartModalEvents(modal) {
    const closeBtn = modal.querySelector('.close');
    const checkoutBtn = modal.querySelector('#checkout-btn');

    closeBtn.onclick = () => modal.style.display = 'none';
    window.onclick = (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };

    checkoutBtn.onclick = () => {
        alert('Gracias por tu compra!');
        cart = [];
        updateCartCount();
        modal.style.display = 'none';
    };
}

window.onload = function () {
    loadCartFromLocalStorage();
    initializeShop();
};