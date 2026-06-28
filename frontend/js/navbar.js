function renderNavbar() {
    const name = localStorage.getItem('name') || '';
    const role = localStorage.getItem('role') || '';
    const token = localStorage.getItem('token');

    const roleMap = {
        admin: 'مشرف',
        host: 'صاحب عقار',
        tenant: 'مستأجر',
    };

    const dashboardMap = {
        admin: 'dashboard-admin.html',
        host: 'dashboard-host.html',
        tenant: 'dashboard-tenant.html',
    };

    const navbar = document.getElementById('navbar');
    if (!navbar) return;

    const guestLinks = `
        <a class="drawer-link drawer-link-primary" href="tenant-login.html"><span><i class="fas fa-user"></i></span><b>تسجيل الدخول</b></a>
        <a class="drawer-link drawer-link-primary" href="host-login.html"><span><i class="fas fa-user-plus"></i></span><b>كن مضيفا</b></a>
        <div class="drawer-divider"></div>
        <a class="drawer-link" href="properties.html"><span><i class="fas fa-home"></i></span><b>الصفحة الرئيسية</b></a>
        <a class="drawer-link" href="properties.html#filters"><span><i class="fas fa-filter"></i></span><b>الفلترة</b></a>
        <a class="drawer-link" href="policies.html"><span><i class="fas fa-file-contract"></i></span><b>السياسات والخصوصية</b></a>
    `;

    const tenantLinks = `
        <a class="drawer-link" href="tenant-properties.html"><span><i class="fas fa-home"></i></span><b>العقارات</b></a>
        <a class="drawer-link" href="dashboard-tenant.html"><span><i class="fas fa-chart-line"></i></span><b>لوحة المستأجر</b></a>
        <a class="drawer-link" href="my-bookings.html"><span><i class="fas fa-calendar-check"></i></span><b>حجوزاتي</b></a>
        <a class="drawer-link" href="my-favorites.html"><span><i class="fas fa-heart"></i></span><b>المفضلة</b></a>
        <a class="drawer-link" href="my-contracts.html"><span><i class="fas fa-file-signature"></i></span><b>عقودي</b></a>
        <a class="drawer-link" href="notifications.html"><span><i class="fas fa-bell"></i></span><b>الإشعارات</b></a>
        <a class="drawer-link" href="profile.html"><span><i class="fas fa-user"></i></span><b>الملف الشخصي</b></a>
        <div class="drawer-divider"></div>
        <button class="drawer-link drawer-button" onclick="logout()"><span><i class="fas fa-sign-out-alt"></i></span><b>تسجيل الخروج</b></button>
    `;

    const hostLinks = `
        <a class="drawer-link" href="dashboard-host.html"><span><i class="fas fa-chart-line"></i></span><b>لوحة المضيف</b></a>
        <a class="drawer-link" href="host-properties.html"><span><i class="fas fa-search"></i></span><b>تصفح المنصة</b></a>
        <a class="drawer-link" href="property-form.html"><span><i class="fas fa-plus-circle"></i></span><b>إضافة عقار</b></a>
        <a class="drawer-link" href="my-properties.html"><span><i class="fas fa-building"></i></span><b>عقاراتي</b></a>
        <a class="drawer-link" href="host-bookings.html"><span><i class="fas fa-calendar-check"></i></span><b>طلبات الحجز</b></a>
        <a class="drawer-link" href="my-contracts-host.html"><span><i class="fas fa-file-signature"></i></span><b>العقود</b></a>
        <a class="drawer-link" href="notifications.html"><span><i class="fas fa-bell"></i></span><b>الإشعارات</b></a>
        <a class="drawer-link" href="profile.html"><span><i class="fas fa-user"></i></span><b>الملف الشخصي</b></a>
        <div class="drawer-divider"></div>
        <button class="drawer-link drawer-button" onclick="logout()"><span><i class="fas fa-sign-out-alt"></i></span><b>تسجيل الخروج</b></button>
    `;

    const adminLinks = `
        <a class="drawer-link" href="dashboard-admin.html"><span><i class="fas fa-chart-line"></i></span><b>لوحة المشرف</b></a>
        <a class="drawer-link" href="admin-properties.html"><span><i class="fas fa-building"></i></span><b>إدارة العقارات</b></a>
        <a class="drawer-link" href="admin-bookings.html"><span><i class="fas fa-calendar-check"></i></span><b>إدارة الحجوزات</b></a>
        <a class="drawer-link" href="notifications.html"><span><i class="fas fa-bell"></i></span><b>الإشعارات</b></a>
        <a class="drawer-link" href="profile.html"><span><i class="fas fa-user"></i></span><b>الملف الشخصي</b></a>
        <div class="drawer-divider"></div>
        <button class="drawer-link drawer-button" onclick="logout()"><span><i class="fas fa-sign-out-alt"></i></span><b>تسجيل الخروج</b></button>
    `;

    const drawerLinks = !token
        ? guestLinks
        : role === 'host'
            ? hostLinks
            : role === 'admin'
                ? adminLinks
                : tenantLinks;

    const drawerLabel = token ? (roleMap[role] || role) : 'زائر';
    const drawerName = token ? (name || drawerLabel) : 'حمى';
    const helpText = token ? 'تحتاج مساعدة؟ تواصل مع الدعم' : 'تصفح العقارات كزائر أو سجّل للمتابعة';
    const homeHref = token && role === 'tenant' ? 'tenant-properties.html' : token && role === 'host' ? 'host-properties.html' : 'properties.html';

    navbar.innerHTML = `
        <nav class="site-nav">
            <div class="nav-start">
                <a class="nav-brand" href="${homeHref}"><span>حمى</span></a>
            </div>
            <a class="nav-home-left" href="${homeHref}" aria-label="الصفحة الرئيسية"><i class="fas fa-home"></i></a>
            <div class="nav-scrim" onclick="closeNavMenu()"></div>
            <div class="nav-drawer" aria-label="القائمة الرئيسية">
                <div class="drawer-head">
                    <a class="drawer-brand" href="${homeHref}">
                        <span class="drawer-logo"><i class="fas fa-home"></i></span>
                        <span>
                            <b>${drawerName}</b>
                            <small>${drawerLabel}</small>
                        </span>
                    </a>
                    <button class="drawer-close" type="button" aria-label="إغلاق القائمة" onclick="closeNavMenu()">×</button>
                </div>
                <div class="drawer-links">
                    ${drawerLinks}
                </div>
                <div class="drawer-help">
                    <b>مساعدة</b>
                    <span>${helpText}</span>
                </div>
            </div>
        </nav>
        <div class="site-nav-spacer"></div>
    `;
}

function toggleNavMenu(button) {
    const nav = button.closest('.site-nav');
    const open = nav.classList.toggle('menu-open');
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
}

function closeNavMenu() {
    document.querySelectorAll('.site-nav.menu-open').forEach(nav => {
        nav.classList.remove('menu-open');
        nav.querySelector('.nav-menu-toggle')?.setAttribute('aria-expanded', 'false');
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderNavbar);
} else {
    renderNavbar();
}
