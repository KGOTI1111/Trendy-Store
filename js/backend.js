/* Trendy Store - MySQL/PHP backend integration
   Put this file in /js/backend.js. It talks only to the PHP API in /api.
*/
const API_BASE = './api/';

async function apiRequest(endpoint, options = {}) {
    const config = { credentials: 'same-origin', ...options };
    config.headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
    const response = await fetch(API_BASE + endpoint, config);
    let result;
    try { result = await response.json(); }
    catch { throw new Error('The server returned an invalid response.'); }
    if (!response.ok || result.success === false) {
        const error = new Error(result.message || 'Request failed.');
        error.status = response.status;
        throw error;
    }
    return result;
}

function backendAlert(message) {
    const box = document.getElementById('customAlert');
    const msg = document.getElementById('alertMsg');
    if (box && msg) {
        msg.textContent = message;
        box.style.display = 'flex';
        setTimeout(() => box.style.display = 'none', 2500);
    } else {
        alert(message);
    }
}

async function getCurrentUser() {
    try {
        const user = (await apiRequest('me.php')).data;
        localStorage.setItem('userProfile', JSON.stringify(user));
        return user;
    } catch {
        localStorage.removeItem('userProfile');
        return null;
    }
}

function goLogin() {
    const returnUrl = encodeURIComponent(window.location.href);
    window.location.href = './login.html?returnUrl=' + returnUrl;
}

function formatMoney(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency', currency: 'INR', maximumFractionDigits: 0
    }).format(Number(value) || 0);
}

/* ---------- Authentication pages ---------- */
function redirectAfterAuth() {
    const params = new URLSearchParams(location.search);
    const redirect = new URL(params.get('returnUrl') || './index.html', location.href);
    const action = params.get('action');
    const productId = params.get('productId');
    if (action) redirect.searchParams.set('action', action);
    if (productId) redirect.searchParams.set('productId', productId);
    redirect.searchParams.set('login', 'success');
    location.href = redirect.href;
}

function redirectToRegistration() {
    const params = new URLSearchParams(location.search);
    const registerParams = new URLSearchParams();
    ['returnUrl', 'action', 'productId'].forEach(key => {
        const value = params.get(key);
        if (value) registerParams.set(key, value);
    });
    const query = registerParams.toString();
    location.href = './register.html' + (query ? '?' + query : '');
}

async function initLoginBackend() {
    const form = document.getElementById('loginForm');
    if (!form) return;

    try {
        const user = await getCurrentUser();
        if (user && !new URLSearchParams(location.search).get('returnUrl')) {
            location.href = './index.html';
            return;
        }
    } catch {}

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const email = document.getElementById('email')?.value.trim() || '';
        const password = document.getElementById('password')?.value || '';
        const emailError = document.getElementById('emailError'), passwordError = document.getElementById('passwordError');
        if (emailError) emailError.textContent = '';
        if (passwordError) passwordError.textContent = '';
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { emailError.textContent = 'Please enter a valid email address.'; return; }
        if (!password) { passwordError.textContent = 'Please enter your password.'; return; }

        try {
            const result = await apiRequest('login.php', {
                method: 'POST',
                body: JSON.stringify({ email, password })
            });
            form.reset();
            if (result.data.role === 'admin') {
                localStorage.removeItem('userProfile');
                location.href = './admin/admin.html';
                return;
            }
            localStorage.setItem('userProfile', JSON.stringify(result.data));
            redirectAfterAuth();
        } catch (error) {
            backendAlert(error.message);
        }
    });
}

async function initRegistrationBackend() {
    const form = document.getElementById('registerForm');
    if (!form) return;

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const name = document.getElementById('fullName').value.trim();
        const email = document.getElementById('email').value.trim();
        const mobile = document.getElementById('mobile').value.trim();
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirmPassword').value;
        const terms = document.getElementById('terms').checked;
        document.querySelectorAll('.error-text').forEach(error => { error.textContent = ''; });
        let valid = true;
        const showError = (id, message) => { document.getElementById(id).textContent = message; valid = false; };
        if (name.length < 3) showError('fullNameError', 'Full name must contain at least 3 characters.');
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) showError('emailError', 'Please enter a valid email address.');
        if (!/^\d{10}$/.test(mobile)) showError('mobileError', 'Mobile number must contain exactly 10 digits.');
        if (password.length < 8 || !/[A-Z]/.test(password) || !/[0-9]/.test(password) || !/[^A-Za-z0-9]/.test(password)) showError('passwordError', 'Use 8+ characters with an uppercase letter, a number and a special character.');
        if (password !== confirm) showError('confirmPasswordError', 'Passwords do not match.');
        if (!terms) showError('termsError', 'Please accept the privacy policy and terms.');
        if (!valid) return;
        try {
            const result = await apiRequest('register.php', { method: 'POST', body: JSON.stringify({ fullName: name, email, mobile, password }) });
            localStorage.setItem('userProfile', JSON.stringify(result.data));
            form.reset();
            redirectAfterAuth();
        } catch (error) { backendAlert(error.message); }
    });
}

function initPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(toggle => {
        toggle.addEventListener('click', () => {
            const input = document.getElementById(toggle.dataset.target);
            if (!input) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            const icon = toggle.querySelector('i');
            if (icon) icon.className = showing ? 'far fa-eye' : 'far fa-eye-slash';
        });
    });
}

/* ---------- Home/products ---------- */
let backendProducts = [];
let backendCurrentProduct = null;

async function loadBackendProducts(category = 'all', search = '') {
    const params = new URLSearchParams();
    if (category && category !== 'all') params.set('category', category);
    if (search) params.set('search', search);
    const result = await apiRequest('products.php?' + params.toString());
    backendProducts = result.data || [];
    return backendProducts;
}

async function loadBackendCategories() {
    const result = await apiRequest('categories.php');
    const filter = document.querySelector('.category-filter');
    if (!filter) return;
    filter.innerHTML = '<button class="filter-btn active" data-category="all" type="button">All Products</button>' +
        (result.data || []).map(category => `<button class="filter-btn" data-category="${escapeHtml(category.name)}" type="button">${escapeHtml(category.name)}</button>`).join('');
}

function renderBackendProducts(items) {
    const grid = document.getElementById('productGrid');
    if (!grid) return;
    grid.innerHTML = '';
    if (!items.length) {
        grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#666;">No products match your search or filter.</p>';
        return;
    }
    items.forEach(p => {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.onclick = () => openBackendModal(p.id);
        card.innerHTML = `
            <div class="product-img-wrapper">
                <span class="badge-category">${p.category}</span>
                <img src="${p.img}" alt="${escapeHtml(p.name)}" class="product-img"
                     onerror="this.onerror=null;this.src='https://placehold.co/520x400?text=Product';">
            </div>
            <div class="product-info">
                <h3>${escapeHtml(p.name)}</h3>
                <div class="price">${formatMoney(p.price)}</div>
            </div>`;
        grid.appendChild(card);
    });
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
    }[c]));
}

function openBackendModal(id) {
    const p = backendProducts.find(x => Number(x.id) === Number(id));
    if (!p) return;
    backendCurrentProduct = p;

    const set = (id, value, html = false) => {
        const el = document.getElementById(id);
        if (el) html ? el.innerHTML = value : el.textContent = value;
    };
    set('modalTitle', p.name);
    set('modalPrice', formatMoney(p.price));
    set('modalDesc', p.desc);
    set('modalSales', `<i class="fas fa-chart-line"></i> ${escapeHtml(p.sales || '')} Sold Last Month`, true);
    set('modalRating', `<i class="fas fa-star"></i> ${escapeHtml(p.rating || '')}`, true);
    set('modalReview', p.review || '', true);

    const main = document.getElementById('modalMainImg');
    if (main) {
        main.src = p.img;
        main.onerror = () => main.src = 'https://placehold.co/700x600?text=Product';
    }
    const thumbs = document.getElementById('modalThumbs');
    if (thumbs) {
        thumbs.innerHTML = '';
        (p.gallery || [p.img]).forEach((url, i) => {
            const img = document.createElement('img');
            img.src = url; img.className = 'thumb-img' + (i === 0 ? ' active' : '');
            img.onerror = () => img.src = 'https://placehold.co/120x100?text=Product';
            img.onclick = () => {
                if (main) main.src = url;
                thumbs.querySelectorAll('.thumb-img').forEach(x => x.classList.remove('active'));
                img.classList.add('active');
            };
            thumbs.appendChild(img);
        });
    }
    const modal = document.getElementById('productModal');
    if (modal) { modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}

function closeBackendModal() {
    const modal = document.getElementById('productModal');
    if (modal) { modal.style.display = 'none'; document.body.style.overflow = 'auto'; }
}

async function backendAddCart(product) {
    const user = await getCurrentUser();
    if (!user) { goLogin(); return; }
    try {
        await apiRequest('cart.php', { method:'POST', body:JSON.stringify({action:'add', productId:Number(product.id)}) });
        backendAlert(`"${product.name}" added to your cart.`);
        closeBackendModal();
        await updateBackendCounts();
    } catch (e) { backendAlert(e.message); }
}

async function backendAddWishlist(product) {
    const user = await getCurrentUser();
    if (!user) { goLogin(); return; }
    try {
        await apiRequest('wishlist.php', { method:'POST', body:JSON.stringify({action:'add', productId:Number(product.id)}) });
        backendAlert(`"${product.name}" added to your Wishlist.`);
        closeBackendModal();
        await updateBackendCounts();
    } catch (e) { backendAlert(e.message); }
}

async function updateBackendCounts() {
    const user = await getCurrentUser();
    if (!user) {
        const w = document.getElementById('wishlistCount'), c = document.getElementById('cartCount');
        if (w) w.textContent = '0'; if (c) c.textContent = '0';
        return;
    }
    try {
        const [w, c] = await Promise.all([apiRequest('wishlist.php'), apiRequest('cart.php')]);
        const wc = document.getElementById('wishlistCount'), cc = document.getElementById('cartCount');
        if (wc) wc.textContent = w.data.length;
        if (cc) cc.textContent = c.data.reduce((sum, x) => sum + Number(x.quantity || 1), 0);
    } catch {}
}

async function initHomeBackend() {
    if (!document.getElementById('productGrid')) return;
    try {
        await loadBackendCategories();
        await loadBackendProducts();
        renderBackendProducts(backendProducts);
        await updateBackendCounts();

        const user = await getCurrentUser();
        const loginLink = document.getElementById('navLoginLink');
        const registerLink = document.getElementById('navRegisterLink');
        const profileLink = document.querySelector('.nav-profile-link');
        if (user) {
            if (loginLink) loginLink.style.display = 'none';
            if (registerLink) registerLink.style.display = 'none';
            if (profileLink) profileLink.style.display = 'flex';
            const name = document.getElementById('homeProfileName');
            const initial = document.getElementById('homeProfileInitial');
            if (name) name.textContent = user.fullName.split(' ')[0];
            if (initial) initial.textContent = user.fullName.charAt(0).toUpperCase();
        } else {
            if (loginLink) loginLink.style.display = 'inline-flex';
            if (registerLink) registerLink.style.display = 'inline-flex';
            if (profileLink) profileLink.style.display = 'none';
        }

        window.openModal = openBackendModal;
        window.closeModal = closeBackendModal;
        window.addToCart = () => backendAddCart(backendCurrentProduct);
        window.addToWishlist = () => backendAddWishlist(backendCurrentProduct);
        window.triggerAction = async (type) => {
            if (!backendCurrentProduct) return;
            if (type === 'Cart') return backendAddCart(backendCurrentProduct);
            if (type === 'Wishlist') return backendAddWishlist(backendCurrentProduct);
            if (type === 'Purchase') {
                const user = await getCurrentUser();
                if (!user) return goLogin();
                localStorage.setItem('buyNowItems', JSON.stringify([{...backendCurrentProduct, quantity:1}]));
                localStorage.setItem('buyNowMode', 'single');
                location.href = './buynow.html';
            }
        };

        // Replace search listener so searches come from MySQL.
        const form = document.getElementById('searchForm');
        if (form) {
            const clone = form.cloneNode(true);
            form.parentNode.replaceChild(clone, form);
            clone.addEventListener('submit', async e => {
                e.preventDefault();
                const q = clone.querySelector('#searchInput')?.value.trim() || '';
                try { await loadBackendProducts('all', q); renderBackendProducts(backendProducts); }
                catch (err) { backendAlert(err.message); }
            });
        }

        // Category buttons can use the existing data-category values.
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const category = btn.dataset.category || 'all';
                try {
                    await loadBackendProducts(category);
                    renderBackendProducts(backendProducts);
                    document.querySelectorAll('.filter-btn').forEach(filterButton => {
                        filterButton.classList.toggle('active', filterButton === btn);
                    });
                    document.getElementById('productsSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } catch (error) {
                    backendAlert(error.message);
                }
            });
        });
    } catch (e) { backendAlert(e.message); }
}

/* ---------- Cart page ---------- */
async function initCartBackend() {
    if (!document.getElementById('cartGrid')) return;
    const user = await getCurrentUser();
    if (!user) { goLogin(); return; }

    async function render() {
        const result = await apiRequest('cart.php');
        const items = result.data || [];
        const grid = document.getElementById('cartGrid');
        const empty = document.getElementById('emptyState');
        const panel = document.getElementById('checkoutPanel');
        if (!items.length) {
            if (grid) grid.innerHTML = '';
            if (empty) empty.style.display = 'block';
            if (panel) panel.style.display = 'none';
            return;
        }
        if (empty) empty.style.display = 'none';
        if (panel) panel.style.display = 'block';
        let total = 0, count = 0;
        grid.innerHTML = '';
        items.forEach(item => {
            total += Number(item.price) * Number(item.quantity);
            count += Number(item.quantity);
            const card = document.createElement('div');
            card.className = 'cart-card';
            card.innerHTML = `
              <img src="${item.img}" alt="${escapeHtml(item.name)}" onerror="this.src='https://placehold.co/520x400?text=Product'">
              <div class="cart-content">
                <h2>${escapeHtml(item.name)}</h2>
                <div class="meta"><span>${escapeHtml(item.category)}</span><span class="price">${formatMoney(item.price)}</span></div>
                <p>${escapeHtml(item.desc || '')}</p>
                <div class="cart-actions">
                  <button class="cart-btn btn-remove" data-remove="${item.id}">Remove</button>
                  <button class="cart-btn btn-back" data-buy="${item.id}">Buy Now</button>
                </div>
                <p style="margin-top:10px;">Quantity: ${item.quantity}</p>
              </div>`;
            card.querySelector('[data-remove]').onclick = async () => {
                await apiRequest('cart.php',{method:'POST',body:JSON.stringify({action:'remove',productId:item.id})});
                render();
            };
            card.querySelector('[data-buy]').onclick = () => {
                localStorage.setItem('buyNowItems', JSON.stringify([{...item, quantity:1}]));
                localStorage.setItem('buyNowMode','single');
                location.href='./buynow.html';
            };
            grid.appendChild(card);
        });
        const ti = document.getElementById('cartTotalItems'), tp = document.getElementById('cartTotalPrice');
        if (ti) ti.textContent = count;
        if (tp) tp.textContent = formatMoney(total);
    }
    window.renderCart = render;
    window.removeFromCart = async id => { await apiRequest('cart.php',{method:'POST',body:JSON.stringify({action:'remove',productId:Number(id)})}); render(); };
    window.buyAllCartItems = async () => {
        const r=await apiRequest('cart.php'); if(!r.data.length) return backendAlert('Your cart is empty.');
        localStorage.setItem('buyNowMode','cart'); location.href='./buynow.html';
    };
    await render();
}

/* ---------- Wishlist page ---------- */
async function initWishlistBackend() {
    if (!document.getElementById('wishlistGrid')) return;
    const user = await getCurrentUser();
    if (!user) { goLogin(); return; }

    async function render() {
        const r=await apiRequest('wishlist.php'), items=r.data||[];
        const grid=document.getElementById('wishlistGrid'), empty=document.getElementById('emptyState');
        grid.innerHTML='';
        if(!items.length){empty.style.display='block';return;}
        empty.style.display='none';
        items.forEach(p=>{
            const card=document.createElement('div');
            card.className='wishlist-card';
            card.innerHTML=`<img src="${p.img}" alt="${escapeHtml(p.name)}" onerror="this.src='https://placehold.co/520x400?text=Product'">
              <div class="wishlist-content"><h2>${escapeHtml(p.name)}</h2>
              <div class="meta"><span>${escapeHtml(p.category)}</span><span class="price">${formatMoney(p.price)}</span></div>
              <p>${escapeHtml(p.desc||'')}</p><div class="wishlist-actions">
              <button class="wishlist-btn btn-remove" data-r="${p.id}">Remove</button>
              <button class="wishlist-btn btn-back" data-c="${p.id}">Add to Cart</button></div></div>`;
            card.querySelector('[data-r]').onclick=async()=>{await apiRequest('wishlist.php',{method:'POST',body:JSON.stringify({action:'remove',productId:p.id})});render();};
            card.querySelector('[data-c]').onclick=async()=>{await apiRequest('cart.php',{method:'POST',body:JSON.stringify({action:'add',productId:p.id})});await apiRequest('wishlist.php',{method:'POST',body:JSON.stringify({action:'remove',productId:p.id})});render();};
            grid.appendChild(card);
        });
    }
    window.renderWishlist=render;
    await render();
}

/* ---------- Profile ---------- */
async function initProfileBackend() {
    if (!document.getElementById('profileForm')) return;
    const user = await getCurrentUser();
    if (!user) { goLogin(); return; }

    const form=document.getElementById('profileForm');
    const name=document.getElementById('editFullName'), email=document.getElementById('editEmail');
    const pass=document.getElementById('editPassword'), confirm=document.getElementById('editConfirmPassword');

    function populate(u) {
        ['displayName','profileName'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=u.fullName;});
        ['displayEmail','profileEmail'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=u.email;});
        const nav=document.getElementById('navUserName'); if(nav)nav.textContent=u.fullName.split(' ')[0];
        const a=document.getElementById('avatarInitial'), al=document.getElementById('avatarLarge');
        if(a)a.textContent=u.fullName.charAt(0).toUpperCase(); if(al)al.textContent=u.fullName.charAt(0).toUpperCase();
        if(name)name.value=u.fullName; if(email)email.value=u.email; if(pass)pass.value=''; if(confirm)confirm.value=''; 
        const terms=document.getElementById('editTerms'); if(terms)terms.checked=true;
    }
    populate(user);

    form.addEventListener('submit',async e=>{
        e.preventDefault();
        if(pass.value && pass.value!==confirm.value){backendAlert('Passwords do not match.');return;}
        try{
            const r=await apiRequest('profile.php',{method:'PUT',body:JSON.stringify({fullName:name.value.trim(),email:email.value.trim(),password:pass.value})});
            form.reset();
            populate(r.data); const m=document.getElementById('formMessage'); if(m)m.textContent='Profile saved successfully.';
            backendAlert('Profile updated successfully!');
        }catch(err){backendAlert(err.message);}
    });
    const logout=document.getElementById('logoutBtn');
    if(logout) logout.onclick=async()=>{
        try { await apiRequest('logout.php',{method:'POST'}); }
        finally { localStorage.removeItem('userProfile'); location.href='./index.html'; }
    };
}

/* ---------- Contact / Inquiry ---------- */
async function initContactBackend() {
    const name=document.getElementById('name'), email=document.getElementById('email'), message=document.getElementById('message');
    if(!name || !email || !message || !document.getElementById('submitPopup')) return;
    const original=window.submitContactMessage;
    window.submitContactMessage=async function(){
        const n=name.value.trim(), e=email.value.trim(), m=message.value.trim();
        if(!n||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)||!m){return original ? original() : backendAlert('Please complete the form.');}
        if(!(await getCurrentUser())){goLogin();return;}
        try{
            await apiRequest('contact.php',{method:'POST',body:JSON.stringify({name:n,email:e,message:m})});
            name.value='';email.value='';message.value='';
            document.getElementById('submitPopup').style.display='flex';
        }catch(err){backendAlert(err.message);}
    };
}

async function initInquiryBackend() {
    const form=document.getElementById('inquiryForm');
    if(!form) return;
    form.addEventListener('submit',async e=>{
        e.preventDefault();
        const name=document.getElementById('inquiry-name')?.value.trim()||'';
        const email=document.getElementById('inquiry-email')?.value.trim()||'';
        const phone=document.getElementById('inquiry-phone')?.value.trim()||'';
        const type=document.getElementById('inquiry-type')?.value||'';
        const message=document.getElementById('inquiry-message')?.value.trim()||'';
        if(!name||!phone||!type||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)||!message){return;}
        if(!(await getCurrentUser())){goLogin();return;}
        try{
            const storedMessage = `Name: ${name}\nPhone: ${phone}\nInquiry type: ${type}\n\n${message}`;
            await apiRequest('inquiry.php',{method:'POST',body:JSON.stringify({email,message:storedMessage})});
            document.getElementById('inquiryPopup').style.display='flex';
            form.reset();
        }catch(err){backendAlert(err.message);}
    });
}

/* ---------- Checkout ---------- */
async function initCheckoutBackend() {
    if(!document.getElementById('checkoutSection')) return;
    const user=await getCurrentUser();
    if(!user){goLogin();return;}

    // Make current user the default delivery identity.
    const name=document.getElementById('fullname'), email=document.getElementById('email');
    if(name)name.value=user.fullName;
    if(email)email.value=user.email;

    let items=[];
    if(localStorage.getItem('buyNowMode')==='single'){
        items=JSON.parse(localStorage.getItem('buyNowItems')||'[]');
    } else {
        const r=await apiRequest('cart.php'); items=r.data||[];
    }
    if(!items.length){
        const empty=document.getElementById('cartEmptyMessage'), section=document.getElementById('checkoutSection');
        if(empty)empty.style.display='block'; if(section)section.style.display='none'; return;
    }
    const section=document.getElementById('checkoutSection'); if(section)section.style.display='block';
    const list=document.getElementById('cartItemsList');
    let subtotal=0;
    if(list){
        list.innerHTML='';
        items.forEach(i=>{
            const q=Number(i.quantity||1), price=Number(i.price);
            subtotal+=price*q;
            const row=document.createElement('div');
            row.style.cssText='display:flex;justify-content:space-between;gap:12px;padding:8px 0;';
            row.innerHTML=`<span>${escapeHtml(i.name)} × ${q}</span><strong>${formatMoney(price*q)}</strong>`;
            list.appendChild(row);
        });
    }
    const shippingSelect=document.getElementById('shipping');
    const updateTotal=()=>{
        const ship=shippingSelect?.value==='express'?150:0;
        const tax=Math.round(subtotal*0.05*100)/100;
        const total=subtotal+ship+tax;
        const set=(id,v)=>{const e=document.getElementById(id);if(e)e.textContent=formatMoney(v);};
        set('subtotal',subtotal);set('shipping-cost',ship);set('tax',tax);set('total',total);
    };
    if(shippingSelect)shippingSelect.addEventListener('change',updateTotal);
    updateTotal();

    window.placeOrder=async function(){
        const required=['fullname','email','phone','address','state','city','area','zipcode','country'];
        for(const id of required){const el=document.getElementById(id);if(!el||!el.value.trim()){backendAlert('Please complete all required delivery fields.');el?.focus();return;}}
        const payment=document.querySelector('input[name="paymentMethod"]:checked')?.value||'cod';
        try{
            const result=await apiRequest('order.php',{method:'POST',body:JSON.stringify({
                items:items.map(i=>({id:Number(i.id),quantity:Number(i.quantity||1)})),
                fullName:name.value.trim(), email:email.value.trim(),
                phone:document.getElementById('phone').value.trim(),
                address:document.getElementById('address').value.trim(),
                area:document.getElementById('area').value.trim(), city:document.getElementById('city').value.trim(),
                state:document.getElementById('state').value.trim(), zipcode:document.getElementById('zipcode').value.trim(),
                country:document.getElementById('country').value.trim(), shipping:shippingSelect?.value||'standard',
                paymentMethod:payment
            })});
            localStorage.removeItem('buyNowItems');localStorage.removeItem('buyNowMode');
            document.querySelectorAll('#checkoutSection input:not([type="radio"]):not([type="checkbox"]), #checkoutSection textarea').forEach(field=>field.value='');
            const modal=document.getElementById('successModal');if(modal)modal.classList.add('active');
            backendAlert('Order #' + result.data.orderId + ' placed successfully.');
        }catch(err){backendAlert(err.message);}
    };
}

document.addEventListener('DOMContentLoaded', () => {
    initLoginBackend();
    initRegistrationBackend();
    initPasswordToggles();
    initHomeBackend();
    initCartBackend();
    initWishlistBackend();
    initProfileBackend();
    initContactBackend();
    initInquiryBackend();
    initCheckoutBackend();
});
