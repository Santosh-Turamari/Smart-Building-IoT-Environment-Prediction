document.addEventListener("DOMContentLoaded", function () {
    const loginText = document.querySelector(".title-text .login");
    const loginForm = document.querySelector("form.login:not(.forgot-email):not(.change-password)");
    const signupForm = document.querySelector("form.signup");
    const loginBtn = document.querySelector("label.login");
    const signupBtn = document.querySelector("label.signup");
    const signupLink = document.querySelector("form .signup-link a");

    // Toggle between login and signup forms
    signupBtn.onclick = () => {
        loginForm.style.marginLeft = "-50%";
        loginText.style.marginLeft = "-50%";
        signupForm.style.display = "block";
        loginForm.style.display = "none";
    };

    loginBtn.onclick = () => {
        loginForm.style.marginLeft = "0%";
        loginText.style.marginLeft = "0%";
        loginForm.style.display = "block";
        signupForm.style.display = "none";
    };

    signupLink.onclick = () => {
        signupBtn.click();
        return false;
    };
});
