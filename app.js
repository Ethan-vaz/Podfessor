// let { courses, courseNotes } = window
let { courses } = require('./courses')
let { courseNotes } = require('./courseNotes')

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
// }) .then(res => res.blob()) // audio comes back as binary
//   .then(blob => {
//     // Turn it into a playable audio element
//     const audioUrl = URL.createObjectURL(blob);
//     const audio = new Audio(audioUrl);
//     audio.play();
//   })
//   .catch(err => console.error(err));