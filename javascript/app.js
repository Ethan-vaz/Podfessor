// Home page: semester filter, search and course cards, all loaded from the API.

let activeSemesterId = ""
let searchText = ""
let latestRequest = 0

let showCardsMessage = (text, className = "") => {
    let cardsContainer = document.querySelector("#cardsContainer")
    cardsContainer.innerHTML = ""
    let message = document.createElement("p")
    message.className = className
    message.textContent = text
    cardsContainer.appendChild(message)
}

let loadCourses = async () => {
    let params = new URLSearchParams()
    if (activeSemesterId) {
        params.set("semester_id", activeSemesterId)
    }
    if (searchText) {
        params.set("q", searchText)
    }

    // ignore replies that arrive after a newer search has started
    let requestNumber = ++latestRequest
    try {
        let { courses } = await apiRequest(`courses.php?${params}`)
        if (requestNumber === latestRequest) {
            generateCourseCards(courses)
        }
    } catch (error) {
        if (requestNumber === latestRequest) {
            showCardsMessage(error.message, "chat-error")
        }
    }
}

let generateSemesterButtons = (semesters) => {
    let semesterContainer = document.querySelector("#semesterContainer")
    semesterContainer.innerHTML = ""

    let options = [{ id: "", name: "All Courses" }, ...semesters]
    options.forEach((semester) => {
        let semesterButton = document.createElement("button")
        semesterButton.className = "button"
        semesterButton.textContent = semester.name
        semesterButton.setAttribute("aria-pressed", String(String(semester.id) === activeSemesterId))

        semesterButton.addEventListener("click", () => {
            activeSemesterId = String(semester.id)
            semesterContainer.querySelectorAll("button").forEach((button) => {
                button.setAttribute("aria-pressed", String(button === semesterButton))
            })
            loadCourses()
        })
        semesterContainer.appendChild(semesterButton)
    })
}

let deleteCourse = async (course) => {
    if (!confirm(`Delete ${course.code} – ${course.name}? This can't be undone.`)) {
        return
    }
    try {
        await apiRequest(`courses.php?id=${course.id}`, { method: "DELETE" })
        loadCourses()
    } catch (error) {
        alert(error.message)
    }
}

let generateCourseCards = (courses) => {
    let cardsContainer = document.querySelector("#cardsContainer")
    cardsContainer.innerHTML = ""
    if (courses.length === 0) {
        showCardsMessage(searchText ? "No courses match your search." : "No courses in this semester yet.")
        return
    }

    courses.forEach((course) => {
        let newCard = document.createElement("div")
        newCard.className = "card"

        if (course.image_url) {
            let newCardImg = document.createElement("img")
            newCardImg.src = course.image_url
            newCardImg.alt = course.name
            newCardImg.loading = "lazy"
            newCard.appendChild(newCardImg)
        }

        let newCardTitle = document.createElement("h4")
        newCardTitle.textContent = course.name
        newCard.appendChild(newCardTitle)

        let newCardCode = document.createElement("span")
        newCardCode.className = "button course-code"
        newCardCode.textContent = course.code
        newCard.appendChild(newCardCode)

        let newCardSemester = document.createElement("p")
        newCardSemester.className = "course-semester"
        newCardSemester.textContent = course.semester
        newCard.appendChild(newCardSemester)

        let newCardP = document.createElement("p")
        newCardP.textContent = course.topics.join(", ")
        newCard.appendChild(newCardP)

        if (course.owned) {
            let actions = document.createElement("div")
            actions.className = "card-actions"

            let editLink = document.createElement("a")
            editLink.href = `newCourse.html?id=${course.id}`
            editLink.textContent = "Edit"
            actions.appendChild(editLink)

            let deleteButton = document.createElement("button")
            deleteButton.className = "link-button"
            deleteButton.textContent = "Delete"
            deleteButton.addEventListener("click", () => deleteCourse(course))
            actions.appendChild(deleteButton)

            newCard.appendChild(actions)
        }

        cardsContainer.appendChild(newCard)
    })
}

document.addEventListener("DOMContentLoaded", async () => {
    let searchForm = document.querySelector("#search-form")
    let searchInput = document.querySelector("#searchInput")
    let searchTimer

    searchForm.addEventListener("submit", (event) => event.preventDefault())
    // wait until the user pauses typing before asking the server
    searchInput.addEventListener("input", () => {
        clearTimeout(searchTimer)
        searchTimer = setTimeout(() => {
            searchText = searchInput.value.trim()
            loadCourses()
        }, 250)
    })

    try {
        let { semesters } = await apiRequest("semesters.php")
        generateSemesterButtons(semesters)
    } catch (error) {
        showCardsMessage(error.message, "chat-error")
        return
    }
    loadCourses()
})
