// Add Course page. With ?id= in the address it edits that course instead.

let courseForm = document.querySelector("#course-form")
let formError = document.querySelector("#formError")
let pageMessage = document.querySelector("#pageMessage")
let courseId = new URLSearchParams(location.search).get("id")

let fillForm = (course) => {
    courseForm.elements.code.value = course.code
    courseForm.elements.name.value = course.name
    courseForm.elements.semester_id.value = course.semester_id
    courseForm.elements.topics.value = course.topics.join(", ")
    courseForm.elements.image_url.value = course.image_url || ""
    courseForm.elements.notes.value = course.notes || ""
}

let setUpForm = async () => {
    let user = await getCurrentUser()
    if (!user) {
        location.href = `login.html?next=${encodeURIComponent("newCourse.html" + location.search)}`
        return
    }

    try {
        let { semesters } = await apiRequest("semesters.php")
        semesters.forEach((semester) => {
            let option = document.createElement("option")
            option.value = semester.id
            option.textContent = semester.name
            courseForm.elements.semester_id.appendChild(option)
        })

        if (courseId) {
            let { course } = await apiRequest(`courses.php?id=${encodeURIComponent(courseId)}`)
            if (!course.owned) {
                pageMessage.textContent = "Sample courses can't be edited. Add your own course instead."
                return
            }
            document.title = "Edit Course – Podfessor"
            document.querySelector("#formTitle").textContent = `Edit ${course.code}`
            fillForm(course)
        }
        courseForm.hidden = false
    } catch (error) {
        pageMessage.textContent = error.message
    }
}

courseForm.addEventListener("submit", async (event) => {
    event.preventDefault()
    formError.textContent = ""
    let button = courseForm.querySelector("button[type=submit]")
    button.disabled = true

    let body = Object.fromEntries(new FormData(courseForm))
    try {
        await apiRequest(courseId ? `courses.php?id=${encodeURIComponent(courseId)}` : "courses.php", {
            method: courseId ? "PUT" : "POST",
            body
        })
        location.href = "index.html"
    } catch (error) {
        formError.textContent = error.message
        showFieldErrors(courseForm, error.fields)
        button.disabled = false
    }
})

setUpForm()
