// Helpers shared by every page: calling the API and showing the account menu.

// Calls one of our API endpoints and returns the JSON body.
// Throws an Error with the server's message (and per-field messages in error.fields) when the request fails.
let apiRequest = async (path, { method = "GET", body } = {}) => {
    let options = { method, headers: {} }
    if (body !== undefined) {
        options.headers["Content-Type"] = "application/json"
        options.body = JSON.stringify(body)
    }

    let response
    try {
        response = await fetch(`api/${path}`, options)
    } catch {
        throw new Error("Could not reach the server. Please check your connection and try again.")
    }

    let data = response.status === 204 ? {} : await response.json().catch(() => ({}))
    if (!response.ok) {
        let error = new Error(data.error || "Something went wrong. Please try again.")
        error.status = response.status
        error.fields = data.fields || {}
        throw error
    }
    return data
}

// Loads the logged-in user once per page. Resolves to null for guests.
let currentUserPromise = null
let getCurrentUser = () => {
    if (!currentUserPromise) {
        currentUserPromise = apiRequest("me.php")
            .then((data) => data.user)
            .catch(() => null)
    }
    return currentUserPromise
}

// Fills the nav's account area: Log in / Sign up for guests, Add course / Log out for users.
let renderAccountMenu = async () => {
    let menu = document.querySelector("#accountMenu")
    if (!menu) {
        return
    }
    let user = await getCurrentUser()
    menu.innerHTML = ""

    let addLink = (text, href, className) => {
        let link = document.createElement("a")
        link.href = href
        link.textContent = text
        if (className) {
            link.className = className
        }
        menu.appendChild(link)
    }

    addLink("About", "about.html")

    if (!user) {
        addLink("Sign Up", "signup.html")
        addLink("Log In", "login.html", "login-button")
        return
    }

    addLink("Add Course", "newCourse.html")

    let greeting = document.createElement("span")
    greeting.textContent = `Hi, ${user.name}`
    menu.appendChild(greeting)

    let logoutButton = document.createElement("button")
    logoutButton.className = "login-button"
    logoutButton.textContent = "Log Out"
    logoutButton.addEventListener("click", async () => {
        await apiRequest("logout.php", { method: "POST" }).catch(() => {})
        location.href = "index.html"
    })
    menu.appendChild(logoutButton)
}

// Shows the server's per-field messages next to the matching form inputs.
let showFieldErrors = (form, fields = {}) => {
    form.querySelectorAll(".field-error").forEach((el) => el.remove())
    form.querySelectorAll("[aria-invalid]").forEach((el) => el.removeAttribute("aria-invalid"))

    Object.entries(fields).forEach(([name, message]) => {
        let input = form.elements[name]
        if (!input) {
            return
        }
        input.setAttribute("aria-invalid", "true")
        let error = document.createElement("p")
        error.className = "field-error"
        error.textContent = message
        input.insertAdjacentElement("afterend", error)
    })
}

document.addEventListener("DOMContentLoaded", renderAccountMenu)
