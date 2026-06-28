const BASE_URL = window.API_BASE_URL || "http://127.0.0.1:8000/api";

function appUrl(path){
    const frontendOrigin = window.location.protocol === "file:"
        ? "http://127.0.0.1:8080"
        : window.location.origin;

    return new URL(path, frontendOrigin + "/").href;
}

function goToPage(path){
    window.location.href = appUrl(path);
}

function saveLogin(data){
    localStorage.setItem("token", data.token);
    localStorage.setItem("role", data.role);
    localStorage.setItem("name", data.user.first_name);
    localStorage.setItem(
        "is_profile_complete",
        data.is_profile_complete
    );
}

function redirectToDashboard(role){
    if(role === "admin"){
        goToPage("dashboard-admin.html");
    }

    if(role === "host"){
        goToPage("dashboard-host.html");
    }

    if(role === "tenant"){
        goToPage("dashboard-tenant.html");
    }
}

function handleLoginSuccess(data){
    saveLogin(data);

    const redirect = getSavedLoginRedirect();

    if(!data.is_profile_complete){
        if(redirect){
            localStorage.setItem("redirect_after_complete", redirect);
        }
        goToPage("complete-profile.html");
        return;
    }

    if(redirect){
        localStorage.removeItem("redirect_after_login");
        goToPage(redirect);
        return;
    }

    redirectToDashboard(data.role);
}

function safeLocalRedirect(value){
    if(!value) return "";
    if(String(value).startsWith("file:")) return "";

    try {
        const url = new URL(value, window.location.href);
        if(url.protocol !== "http:" && url.protocol !== "https:") return "";
        if(url.origin !== window.location.origin) return "";
        return url.pathname.replace(/^\//, "") + url.search + url.hash;
    } catch (e) {
        return "";
    }
}

function captureLoginRedirect(){
    const params = new URLSearchParams(window.location.search);
    const redirect = safeLocalRedirect(params.get("redirect"));

    if(redirect){
        localStorage.setItem("redirect_after_login", redirect);
    }
}

function getSavedLoginRedirect(){
    const redirect = safeLocalRedirect(localStorage.getItem("redirect_after_login"));
    if(!redirect){
        localStorage.removeItem("redirect_after_login");
    }

    return redirect;
}

async function loginWithRole(expectedRole){
    const body = {
        email: email.value,
        password: password.value
    };

    const res = await fetch(
        BASE_URL + "/login",
        {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify(body)
        }
    );

    const data = await res.json();

    if(!res.ok){
        msg.innerHTML = '<div class="error">بيانات غير صحيحة</div>';
        return;
    }

    if(expectedRole && data.role !== expectedRole){
        msg.innerHTML = expectedRole === "tenant"
            ? '<div class="error">هذا الدخول مخصص للمستأجرين</div>'
            : '<div class="error">هذا الدخول مخصص للمضيفين</div>';
        return;
    }

    handleLoginSuccess(data);
}

function checkProfileComplete(){
    const complete =
      localStorage.getItem("is_profile_complete")==="true";

    if(!complete){
        localStorage.setItem(
            "redirect_after_complete",
            window.location.href
        );

        goToPage("complete-profile.html");
        return false;
    }

    return true;
}

function requireAuth(loginPage = "tenant-login.html"){
    if(!localStorage.getItem("token")){
        const redirect = safeLocalRedirect(window.location.href);
        if(redirect){
            localStorage.setItem("redirect_after_login", redirect);
        }
        window.location.replace(appUrl(loginPage));
        throw new Error("Authentication required");
    }

    return true;
}

function authHeaders(){
    return {
        "Content-Type":"application/json",
        "Accept":"application/json",
        "Authorization":
          "Bearer " + localStorage.getItem("token")
    };
}

//LOGOUT 
async function logout() {
    const token = localStorage.getItem('token');

    if (token) {
        try {
            await fetch(BASE_URL + '/logout', {
                method: 'POST',
                headers: authHeaders(),
            });
        } catch (e) {
            
        }
    }
    localStorage.clear();
    goToPage('login.html');
}
