// Log in and Sign up forms. Both send JSON to the API, then go back to where the user was headed.

let authForm = document.querySelector("#auth-form")
let formError = document.querySelector("#formError")
let isSignup = authForm.dataset.mode === "signup"

// Only follow ?next= when it points at one of our own pages, so the link can't send users to another site.
let nextPage = () => {
    let next = new URLSearchParams(location.search).get("next") || ""
    return /^[A-Za-z0-9_-]+\.html(\?[A-Za-z0-9_=&-]*)?$/.test(next) ? next : "index.html"
}

authForm.addEventListener("submit", async (event) => {
    event.preventDefault()
    formError.textContent = ""
    let button = authForm.querySelector("button[type=submit]")
    button.disabled = true

    let body = Object.fromEntries(new FormData(authForm))
    try {
        await apiRequest(isSignup ? "register.php" : "login.php", { method: "POST", body })
        location.href = nextPage()
    } catch (error) {
        formError.textContent = error.message
        showFieldErrors(authForm, error.fields)
        button.disabled = false
    }
})

// carry ?next= over when switching between the log in and sign up pages
document.querySelectorAll('a[href="login.html"], a[href="signup.html"]').forEach((link) => {
    if (location.search) {
        link.href += location.search
    }
})
