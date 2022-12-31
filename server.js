//1. Require express to create an instance of the application.
//2. Create an instance of the express application.
//3. Require the sitemap module to generate sitemaps.
//4. Create a port to listen to requests.
//5. Create a get request to serve the index page.
//6. Create a post request to create a sitemap.
//7. Asynchonously call the sitemap generator to create the sitemap.
//8. Return the generated sitemap to the client.
//9. Listen to the port to serve requests.
//10. Log successful port listening.

const express = require("express");
const app = express();
const sitemap = require("./sitemap");
const PORT = process.env.PORT || 3000;

app.use(express.urlencoded({ extended: true }));
app.use(express.json());

console.error("Starting server");
app.get("/", function (req, res) {
	res.sendFile(__dirname + "/index.html");
});

app.get("/test", (req, res) => {
	const url = req.query.url;

	if (url) {
		console.log("URL parameter detected:", url);
	}

	res.send(
        `<!doctype html>
        <html>
            <head>
                <title>My Page</title>
            </head>
            <body>
                <p>Hello, World! ` + url + `</p>
            </body>
        </html>`
	);
});

app.get("/json", (req, res) => {
	const { url } = req.query;

	if (!url) {
		return res.status(400).send({ success: false, message: "URL parameter is required" });
	}

	createSitemap(url)
		.then((sitemap) => {
			res.send({ success: true, url: url, sitemap: sitemap });
		})
		.catch((err) => {
			res.status(500).send({ success: false, url: url, message: "Error creating sitemap" });
		});

        
});

const createSitemap = (domain) => {
	return new Promise((resolve, reject) => {
		sitemap
			.generate(domain)
			.then((urls) => {
				resolve(urls);
			})
			.catch((err) => {
				reject(err);
			});
	});
};

app.get("/main.js", (req, res) => {
	res.sendFile(__dirname + "/main.js");
});

app.post("/create", async (req, res) => {
	console.log(res.req.body.domain);
	const main = async () => {
		const domain = res.req.body.domain;
		const urls = await sitemap.generate(domain);

		return urls;
	};

	const urls = await main();

	console.log("urls2");
	console.log(urls);

	res.json({
		success: true,
		message: "Sitemap generated successfully",
		data: JSON.stringify(urls),
	});
});

app.listen(PORT, () => {
	console.log(`Server listening on port ${PORT}`);
});
