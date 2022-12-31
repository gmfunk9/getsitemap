//1. This code is used to generate a list of all URLs on a website. 
//2. It uses an external library called Sitemapper to fetch the URLs from a sitemap.xml file. 
//3. The code also sets a User-Agent header in the request to the website, which identifies the type of device used to make the request. 
//4. The URLs are then sorted alphabetically and returned as an array. 
//5. This list of URLs can then be used for website analysis or other purposes.
const Sitemapper = require("sitemapper");
const userAgent  = require("user-agent");

const options = {
	headers: {
		"User-Agent": userAgent.toString(),
	},
};

const generate = async (domain) => {
	// await new Promise(r => setTimeout(r, 5000));
	let urls;
	const sitemap = new Sitemapper();
	const withHttp = (url) => url.replace(/^(?:(.*:)?\/\/)?(.*)/i, (match, schema, nonschemaUrl) => (schema ? match : `https://${nonschemaUrl}`));
	domain = withHttp(domain);
    if (!domain.endsWith(".xml")) {
        domain = domain + "/sitemap_index.xml";
    }
	try {
		const data = await sitemap.fetch(domain, options);
		urls = data.sites;
	} catch (error) {
		console.error(error);
	}
	urls.sort((a, b) => a.localeCompare(b));

	return urls;
};

module.exports = {
	generate,
};
