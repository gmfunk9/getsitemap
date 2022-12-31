// main.js
// document.addEventListener("DOMContentLoaded", () => { waits for the DOM (Document Object Model) to be fully loaded, and then runs the code inside the callback function.
// const form = document.getElementById("sitemapForm"); selects the form element with the ID "sitemapForm".
// form.addEventListener("submit", (e) => { adds an event listener to the form that listens for a submit event. When the form is submitted, the callback function runs, which prevents the default behavior of the form submission (e.preventDefault()) and sends a POST request to the "/create" route with the form data.
// fetch("/create", {...}) sends a POST request to the "/create" route with the form data as the body of the request. The response from the server is then parsed as JSON and processed in the .then() block. If the request is successful and the server returns a "success" field in the response data, the sitemap contents are rendered in the response div. 
// If the request is unsuccessful or the server returns an error, an alert message is displayed.

console.log("funkpd");
document.addEventListener("DOMContentLoaded", () => {
  
  const form = document.getElementById("sitemapForm");
  
  const submitButton = document.querySelector(".form__submit");
  
  const responseDiv = document.getElementById("response");
  
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    
    const domain = document.getElementById("domain").value;
    
    submitButton.disabled = true;
    
    submitButton.innerHTML =
      '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
    
    fetch("/create", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ domain }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          console.log(data.message);
          console.log(data.data);
          JSON.parse(data.data).forEach((element) => {
            responseDiv.innerHTML += "<p>" + element + "</p>";
          });
        } else {
          alert("Something went wrong. Please try again.");
        }
        
        submitButton.disabled = false;
        
        submitButton.innerHTML = "Create Sitemap";
      })
      .catch((err) => {
        console.log(err);
        alert("Something went wrong. Please try again.");
        
        submitButton.disabled = false;
        
        submitButton.innerHTML = "Create Sitemap";
      });
  });
});
