// Sends the student's question to our own server, which calls OpenAI with the secret key.

let chatForm = document.querySelector("#chat-form")
let chatInput = document.querySelector("#chatInput")
let chatButton = chatForm.querySelector("button")
let chatResponses = document.querySelector("#chatResponses")

let showChatMessage = (text, className) => {
    let message = document.createElement("p")
    message.className = className
    message.textContent = text
    chatResponses.appendChild(message)
}

chatForm.addEventListener("submit", (event) => {
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

    fetch("api/chat.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ prompt })
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.error) {
                showChatMessage(data.error, "chat-error")
            } else {
                showChatMessage(data.text, "chat-reply")
                chatInput.value = ""
            }
        })
        .catch(() => showChatMessage("Could not reach the server. Please try again.", "chat-error"))
        .finally(() => {
            loading.remove()
            chatButton.disabled = false
        })
})
