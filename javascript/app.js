let { courses, courseNotes } = window

let generateCourseCards = (courses) => {
    courses.forEach((course) => {
        console.log(course.name)
        // div
        let newCard = document.createElement("div")

        // img
        let newCardImg = document.createElement("img")
        newCardImg.src = course.image
        
        // h4 title
        let newCardTitle = document.createElement("h4")
        newCardTitle.textContent = course.name

        // button
        let newCardButton = document.createElement("button")
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
        let body = document.querySelector("body")
        body.appendChild(newCard)
    })
}

document.addEventListener("DOMContentLoaded", (event) => {
    console.log("DOM fully loaded and parsed");
    console.log("Courses:", courses)
    console.log("Course Notes:", courseNotes)

    let generateButton = document.querySelector("#generateButton")
    generateButton.addEventListener("click", () => {
        console.log("you clicked me!")
        generateCourseCards(courses)
    })

    // 
});


// let { courses } = require('./courses')
// let { courseNotes } = require('./courseNotes')

// console.log("Courses:", courses)

// courses.forEach((course) => {
//     console.log(`Course: ${course.code} - ${course.name}`)
// })

// courseNotes.forEach((note) => {
//     let notesArray = note.notes.split(" ")
//     console.log(`Course: ${note.code} - Notes Word Count: ${notesArray.length}`)
// })

// consider elevenLabs API for text to speech 

// fetch("https://api.openai.com/v1/audio/speech", {
//     method: 'POST',
//     headers: {
//         "Content-Type": "application/json",
//         "Authorization": "Bearer $OPEN_AI_API_KEY" 
//     },
//     body: JSON.stringify(chatInputs)
// TODO: add chatInputs 
// }) .then(res => res.blob()) // audio comes back as binary
//   .then(blob => {
//     // Turn it into a playable audio element
//     const audioUrl = URL.createObjectURL(blob);
//     const audio = new Audio(audioUrl);
//     audio.play();
//   })
//   .catch(err => console.error(err));