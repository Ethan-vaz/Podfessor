// "Ask Podfessor" panel: ask a question, get a podcast script, then press Listen to hear it.
// Every script is saved as an episode, and the list below the form shows your recent ones.

let chatForm = document.querySelector("#chat-form")
let chatInput = document.querySelector("#chatInput")
let chatCourse = document.querySelector("#chatCourse")
let chatButton = chatForm.querySelector("button")
let chatStatus = document.querySelector("#chatStatus")
let episodeList = document.querySelector("#episodeList")

let showChatStatus = (text, className = "") => {
    chatStatus.className = className
    chatStatus.textContent = text
}

let addAudioPlayer = (card, audioUrl, autoplay) => {
    let player = document.createElement("audio")
    player.controls = true
    player.preload = "none"
    player.src = audioUrl
    card.querySelector(".episode-audio").replaceChildren(player)
    if (autoplay) {
        player.play().catch(() => {
            // the browser blocked autoplay; the controls are still there to press play
        })
    }
}

let listen = async (episode, card, button) => {
    button.disabled = true
    button.textContent = "Recording audio..."
    try {
        let data = await apiRequest("speech.php", { method: "POST", body: { episode_id: episode.id } })
        episode.audio_url = data.episode.audio_url
        addAudioPlayer(card, episode.audio_url, true)
    } catch (error) {
        button.disabled = false
        button.textContent = "🎧 Listen"
        let message = document.createElement("p")
        message.className = "chat-error"
        message.textContent = error.message
        card.querySelector(".episode-audio").appendChild(message)
    }
}

let deleteEpisode = async (episode, card) => {
    if (!confirm(`Delete the episode "${episode.prompt}"?`)) {
        return
    }
    try {
        await apiRequest(`episodes.php?id=${episode.id}`, { method: "DELETE" })
        card.remove()
        if (!episodeList.children.length) {
            showChatStatus("No episodes yet. Ask a question to make your first one.")
        }
    } catch (error) {
        alert(error.message)
    }
}

let createEpisodeCard = (episode, { open = false } = {}) => {
    let card = document.createElement("article")
    card.className = "episode"

    let title = document.createElement("h6")
    title.textContent = episode.prompt
    card.appendChild(title)

    let meta = document.createElement("p")
    meta.className = "episode-meta"
    let date = new Date(episode.created_at.replace(" ", "T")).toLocaleDateString()
    meta.textContent = episode.course_code ? `${episode.course_code} · ${date}` : date
    card.appendChild(meta)

    let audio = document.createElement("div")
    audio.className = "episode-audio"
    card.appendChild(audio)

    if (episode.audio_url) {
        addAudioPlayer(card, episode.audio_url, false)
    } else {
        let listenButton = document.createElement("button")
        listenButton.className = "button"
        listenButton.textContent = "🎧 Listen"
        listenButton.addEventListener("click", () => listen(episode, card, listenButton))
        audio.appendChild(listenButton)
    }

    let script = document.createElement("details")
    script.open = open
    let summary = document.createElement("summary")
    summary.textContent = "Read the script"
    let scriptText = document.createElement("p")
    scriptText.className = "chat-reply"
    scriptText.textContent = episode.script
    script.append(summary, scriptText)
    card.appendChild(script)

    let deleteButton = document.createElement("button")
    deleteButton.className = "link-button"
    deleteButton.textContent = "Delete"
    deleteButton.addEventListener("click", () => deleteEpisode(episode, card))
    card.appendChild(deleteButton)

    return card
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

let loadEpisodes = async () => {
    try {
        let { episodes } = await apiRequest("episodes.php")
        episodes.forEach((episode) => episodeList.appendChild(createEpisodeCard(episode)))
        if (!episodes.length) {
            showChatStatus("No episodes yet. Ask a question to make your first one.")
        }
    } catch (error) {
        showChatStatus(error.message, "chat-error")
    }
}

let setUpChat = async () => {
    let user = await getCurrentUser()
    if (!user) {
        chatForm.hidden = true
        chatStatus.innerHTML = '<a href="login.html">Log in</a> or <a href="signup.html">sign up</a> to ask Podfessor about your courses.'
        return
    }
    loadChatCourses()
    loadEpisodes()
}

chatForm.addEventListener("submit", async (event) => {
    event.preventDefault()

    let prompt = chatInput.value.trim()
    if (!prompt) {
        return
    }

    chatButton.disabled = true
    showChatStatus("Writing your podcast script...", "chat-loading")

    try {
        let body = { prompt }
        if (chatCourse.value) {
            body.course_id = Number(chatCourse.value)
        }
        let { episode } = await apiRequest("chat.php", { method: "POST", body })
        showChatStatus("")
        episodeList.prepend(createEpisodeCard(episode, { open: true }))
        chatInput.value = ""
    } catch (error) {
        showChatStatus(error.message, "chat-error")
    } finally {
        chatButton.disabled = false
    }
})

setUpChat()
