/* ADMINISTRATIVE INTERFACE ONLY: customer-facing behavior remains in /js/backend.js. */
const ADMIN_API = '../api/admin.php';
const ADMIN_LOGIN_API = '../api/admin_login.php';
const ADMIN_LOGOUT_API = '../api/admin_logout.php';
const money = value => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(Number(value) || 0);
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[character]));

async function adminRequest(options = {}) {
    const response = await fetch(ADMIN_API, { credentials: 'same-origin', ...options, headers: { 'Content-Type': 'application/json', ...(options.headers || {}) } });
    const result = await response.json();
    if (!response.ok || result.success === false) { const error = new Error(result.message || 'Administrator request failed.'); error.status = response.status; throw error; }
    return result;
}

function showAlert(message, isError = false) {
    const alert = document.getElementById('adminAlert');
    alert.textContent = message;
    alert.style.background = isError ? 'var(--danger)' : '#2f8f56';
    alert.classList.add('show');
    setTimeout(() => alert.classList.remove('show'), 3000);
}

function togglePasswordButtons() {
    document.querySelectorAll('.password-toggle').forEach(button => button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.target);
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        button.querySelector('i').className = showing ? 'far fa-eye' : 'far fa-eye-slash';
    }));
}

function setLoggedIn(admin) {
    document.getElementById('adminLogin').hidden = true;
    document.getElementById('adminApp').hidden = false;
    document.getElementById('adminName').textContent = admin.name;
    document.querySelector('.avatar').textContent = admin.name.charAt(0).toUpperCase();
    loadOverview();
}

function setLoginError(message) {
    document.getElementById('adminPasswordError').textContent = message;
}

async function restoreAdminSession() {
    const response = await fetch(ADMIN_LOGIN_API, { credentials: 'same-origin' });
    const result = await response.json();
    if (response.ok && result.success) setLoggedIn(result.data);
}

async function loadOverview() {
    const result = await adminRequest({ method: 'GET' });
    document.getElementById('userCount').textContent = result.data.users;
    document.getElementById('productCount').textContent = result.data.products;
    document.getElementById('orderCount').textContent = result.data.orders;
}

async function loadUsers() {
    const response = await fetch(`${ADMIN_API}?section=users`, { credentials: 'same-origin' });
    const data = await response.json();
    if (!response.ok || data.success === false) throw new Error(data.message || 'Users could not be loaded.');
    document.getElementById('usersTable').innerHTML = data.data.length ? data.data.map(user => `<tr><td><strong>${escapeHtml(user.fullName)}</strong><br><small>#${user.id}</small></td><td>${escapeHtml(user.email)}</td><td>${escapeHtml(user.mobile)}</td><td>${new Date(user.createdAt).toLocaleDateString()}</td><td><button class="table-action delete" data-delete-user="${user.id}"><i class="fas fa-trash"></i> Delete</button></td></tr>`).join('') : '<tr><td colspan="5">No customers registered yet.</td></tr>';
    document.querySelectorAll('[data-delete-user]').forEach(button => button.onclick = () => deleteUser(button.dataset.deleteUser));
}

async function loadProducts() {
    const response = await fetch(`${ADMIN_API}?section=products`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || result.success === false) throw new Error(result.message || 'Products could not be loaded.');
    document.getElementById('productsTable').innerHTML = result.data.length ? result.data.map(product => `<tr><td><div class="product-cell"><img src="../${escapeHtml(product.image)}" alt=""><strong>${escapeHtml(product.name)}</strong></div></td><td>${escapeHtml(product.category)}</td><td>${money(product.price)}</td><td>${escapeHtml(product.sales || '0')}</td><td><button class="table-action" data-edit-product="${product.id}"><i class="fas fa-pen"></i> Edit</button><button class="table-action delete" data-delete-product="${product.id}"><i class="fas fa-trash"></i> Delete</button></td></tr>`).join('') : '<tr><td colspan="5">No products found.</td></tr>';
    result.data.forEach(product => { document.querySelector(`[data-edit-product="${product.id}"]`).onclick = () => openProductDialog(product); });
    document.querySelectorAll('[data-delete-product]').forEach(button => button.onclick = () => deleteProduct(button.dataset.deleteProduct));
}

async function loadOrders() {
    const response = await fetch(`${ADMIN_API}?section=orders`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || result.success === false) throw new Error(result.message || 'Orders could not be loaded.');
    const statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    document.getElementById('ordersTable').innerHTML = result.data.length ? result.data.map(order => `<tr><td><strong>#${order.id}</strong><br><small>${new Date(order.createdAt).toLocaleDateString()}</small></td><td>${escapeHtml(order.fullName)}<br><small>${escapeHtml(order.email)}</small></td><td>${escapeHtml(order.city)}, ${escapeHtml(order.state)}</td><td>${money(order.total)}</td><td><span class="status">${escapeHtml(order.status)}</span></td><td><select data-order-status="${order.id}">${statuses.map(status => `<option ${status === order.status ? 'selected' : ''}>${status}</option>`).join('')}</select></td></tr>`).join('') : '<tr><td colspan="6">No orders found.</td></tr>';
    document.querySelectorAll('[data-order-status]').forEach(select => select.onchange = () => updateOrderStatus(select.dataset.orderStatus, select.value));
}

function openProductDialog(product = null) {
    document.getElementById('productDialogTitle').textContent = product ? 'Edit product' : 'Add product';
    document.getElementById('productId').value = product?.id || '';
    document.getElementById('productName').value = product?.name || '';
    document.getElementById('productCategory').value = product?.category || '';
    document.getElementById('productPrice').value = product?.price || '';
    document.getElementById('productSales').value = product?.sales || '';
    document.getElementById('productRating').value = product?.rating || '';
    document.getElementById('productImage').value = product?.image || '';
    document.getElementById('productDescription').value = product?.description || '';
    document.getElementById('productReview').value = product?.review || '';
    document.getElementById('productDialog').showModal();
}

async function saveProduct(event) {
    event.preventDefault();
    const body = { action:'save_product', id:document.getElementById('productId').value, name:document.getElementById('productName').value.trim(), category:document.getElementById('productCategory').value.trim(), price:document.getElementById('productPrice').value, sales:document.getElementById('productSales').value.trim(), rating:document.getElementById('productRating').value.trim(), image:document.getElementById('productImage').value.trim(), description:document.getElementById('productDescription').value.trim(), review:document.getElementById('productReview').value.trim() };
    try { await adminRequest({ method:'POST', body:JSON.stringify(body) }); document.getElementById('productDialog').close(); await loadProducts(); await loadOverview(); showAlert('Product saved.'); } catch (error) { showAlert(error.message, true); }
}

async function deleteProduct(id) { if (!confirm('Delete this product?')) return; try { await adminRequest({ method:'POST', body:JSON.stringify({ action:'delete_product', id }) }); await loadProducts(); await loadOverview(); showAlert('Product deleted.'); } catch (error) { showAlert(error.message, true); } }
async function deleteUser(id) { if (!confirm('Delete this customer and their saved operations?')) return; try { await adminRequest({ method:'POST', body:JSON.stringify({ action:'delete_user', id }) }); await loadUsers(); await loadOverview(); showAlert('Customer deleted.'); } catch (error) { showAlert(error.message, true); } }
async function updateOrderStatus(id, status) { try { await adminRequest({ method:'POST', body:JSON.stringify({ action:'update_order_status', id, status }) }); await loadOrders(); showAlert('Order status updated.'); } catch (error) { showAlert(error.message, true); } }

async function selectSection(section) {
    document.querySelectorAll('.nav-item').forEach(button => button.classList.toggle('active', button.dataset.section === section));
    document.querySelectorAll('.content-section').forEach(panel => panel.classList.toggle('active', panel.id === `${section}Section`));
    document.getElementById('pageTitle').textContent = section.charAt(0).toUpperCase() + section.slice(1);
    try { if (section === 'overview') await loadOverview(); if (section === 'products') await loadProducts(); if (section === 'orders') await loadOrders(); if (section === 'users') await loadUsers(); } catch (error) { showAlert(error.message, true); }
}

document.addEventListener('DOMContentLoaded', async () => {
    togglePasswordButtons();
    document.getElementById('adminLoginForm').addEventListener('submit', async event => {
        event.preventDefault();
        document.getElementById('adminEmailError').textContent = '';
        setLoginError('');
        try {
            const result = await fetch(ADMIN_LOGIN_API, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: document.getElementById('adminEmail').value.trim(),
                    password: document.getElementById('adminPassword').value
                })
            });
            const data = await result.json();
            if (!result.ok || data.success === false) {
                const error = new Error(data.message || 'Administrator login failed.');
                error.status = result.status;
                throw error;
            }
            setLoggedIn(data.data);
        } catch (error) {
            setLoginError(error.message);
        }
    });
    document.querySelectorAll('.nav-item').forEach(button => button.onclick = () => selectSection(button.dataset.section));
    document.getElementById('newProduct').onclick = () => openProductDialog();
    document.getElementById('productForm').addEventListener('submit', saveProduct);
    document.getElementById('closeDialog').onclick = () => document.getElementById('productDialog').close();
    document.getElementById('cancelDialog').onclick = () => document.getElementById('productDialog').close();
    document.getElementById('adminLogout').onclick = async () => {
        await fetch(ADMIN_LOGOUT_API, { method: 'POST', credentials: 'same-origin' });
        location.reload();
    };
    try { await restoreAdminSession(); } catch {}
});
