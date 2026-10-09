
let { courses, courseNotes, semesters } = window

let generateSemesterButtons = (semesters) => {

    let semesterContainer = document.querySelector("#semesterContainer")

    let allButton = document.createElement("button")
    allButton.className = "button"
    allButton.textContent = "All Courses"
    allButton.addEventListener("click", () => generateCourseCards(courses))
    semesterContainer.appendChild(allButton)

    semesters.forEach((semester) => {
        let semesterButton = document.createElement("button")
        semesterButton.className = "button"
        semesterButton.textContent = semester.name

        semesterButton.addEventListener("click", () => {
            // console.log(`You clicked on semester: ${semester.semesterID}`)  
            // filter courses based on semesterID
            let filteredCourses = courses.filter((course) => {
                return course.semesterID === semester.semesterID
            })
            // console.log(filteredCourses)
            generateCourseCards(filteredCourses)

        })
        semesterContainer.appendChild(semesterButton)
    })
}



let generateCourseCards = (courses) => {
    // clear previous cards
    let cardsContainer = document.querySelector("#cardsContainer")
    cardsContainer.innerHTML = ""
    if (courses.length === 0) {
        let emptyMessage = document.createElement("p")
        emptyMessage.textContent = "No courses match your search."
        cardsContainer.appendChild(emptyMessage)
        return
    }
    courses.forEach((course) => {
        // div
        let newCard = document.createElement("div")
        if (course.outlineURL) {
            newCard.onclick = () => {
                open(course.outlineURL, "_blank", "noopener,noreferrer")
            }
        }

        // img
        let newCardImg = document.createElement("img")
        newCardImg.src = course.image
        newCardImg.alt = course.name
        newCardImg.loading = "lazy"
        
        // h4 title
        let newCardTitle = document.createElement("h4")
        newCardTitle.textContent = course.name

        // button
        let newCardButton = document.createElement("button")
        // newCardButton.onclick = () => {
        //     open(course.outlineURL, "_blank", "noopener,noreferrer")
        // }
        newCardButton.className = "button"
        newCardButton.textContent = course.code  

        // text / paragraph
        let newCardP = document.createElement("p")
        newCardP.textContent = course.topics.join(", ")

        // append children to card
        newCard.appendChild(newCardImg)
        newCard.appendChild(newCardTitle)
        newCard.appendChild(newCardButton)
        newCard.appendChild(newCardP)

        // styling the card
        newCard.className = "card"

        // appending to body
        let cardsContainer = document.querySelector("#cardsContainer")
        cardsContainer.appendChild(newCard)
    })
}

// show courses whose name, code or topics contain the search text
let searchCourses = (query) => {
    let text = query.trim().toLowerCase()
    return courses.filter((course) => {
        let searchable = [course.name, course.code, ...course.topics].join(" ").toLowerCase()
        return searchable.includes(text)
    })
}

document.addEventListener("DOMContentLoaded", () => {
    generateSemesterButtons(semesters)
    generateCourseCards(courses)

    let searchForm = document.querySelector("#search-form")
    let searchInput = document.querySelector("#searchInput")
    searchForm.addEventListener("submit", (event) => event.preventDefault())
    searchInput.addEventListener("input", () => {
        generateCourseCards(searchCourses(searchInput.value))
    })
});

// consider elevenLabs API for text to speech
