// "Ask Podfessor" panel: sends the question (and optionally a course) to our server, which calls OpenAI.

let chatForm = document.querySelector("#chat-form")
let chatInput = document.querySelector("#chatInput")
let chatCourse = document.querySelector("#chatCourse")
let chatButton = chatForm.querySelector("button")
let chatResponses = document.querySelector("#chatResponses")

let showChatMessage = (text, className) => {
    let message = document.createElement("p")
    message.className = className
    message.textContent = text
    chatResponses.appendChild(message)
}

// fill the course picker so answers can use that course's topics and notes
let loadChatCourses = async () => {
    try {
        let { courses } = await apiRequest("courses.php")
        courses.forEach((course) => {
            let option = document.createElement("option")
            option.value = course.id
            option.textContent = `${course.code} – ${course.name}`
            chatCourse.appendChild(option)
        })
    } catch {
        // the picker is optional, so the chat still works without it
    }
}

let setUpChat = async () => {
    let user = await getCurrentUser()
    if (!user) {
        chatForm.hidden = true
        let prompt = document.createElement("p")
        prompt.innerHTML = '<a href="login.html">Log in</a> or <a href="signup.html">sign up</a> to ask Podfessor about your courses.'
        chatResponses.appendChild(prompt)
        return
    }
    loadChatCourses()
}

chatForm.addEventListener("submit", async (event) => {
    event.preventDefault()

    let prompt = chatInput.value.trim()
    if (!prompt) {
        return
    }

    chatButton.disabled = true
    let loading = document.createElement("p")
    loading.className = "chat-loading"
    loading.textContent = "Writing your podcast script..."
    chatResponses.appendChild(loading)

    try {
        let body = { prompt }
        if (chatCourse.value) {
            body.course_id = Number(chatCourse.value)
        }
        let data = await apiRequest("chat.php", { method: "POST", body })
        showChatMessage(data.text, "chat-reply")
        chatInput.value = ""
    } catch (error) {
        showChatMessage(error.message, "chat-error")
    } finally {
        loading.remove()
        chatButton.disabled = false
    }
})

setUpChat()
