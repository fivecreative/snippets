import * as fs from 'node:fs';
import path from 'node:path';

// Ignore the following folders when building the index
const ignoreFolders = [
	'src',
	'.idea',
	'.git',
	'.DS_Store',
	'.empty'
];

const capitalizeFirstLetter = function (val) {
	return String(val).charAt(0).toUpperCase() + String(val).slice(1);
}

// Retrieves title and description for a file
const getSnippetInfo = (dirent) => {

	const fileContent = fs.readFileSync(path.resolve(dirent.path, dirent.name), 'utf8');

	const fileBlock = fileContent.match(/\/\*{2}[\s\S]+(?=\*\/)/gm);
	
	if(!fileBlock || !fileBlock.length) {
		console.error('Unable to get title and description from file '+path.resolve(dirent.path, dirent.name));
		return false;
	}
	
	const title = fileBlock[0].match(/\*\s?title:\s?([^\r\n]+)/g);
	const description = fileBlock[0].match(/\*\s?description:\s?([^\r\n]+)/g);
	
	if(!title || !title.length || !description || !description.length) {
		console.error('Unable to get title and description from file '+path.resolve(dirent.path, dirent.name));
		return false;
	}
	
	return {
		title: title[0].replace(/\*\s*title:\s*/g, '').trim(),
		description: description[0].replace(/\*\s*description:\s*/g, '').trim(),
	}

}

// Generates markdown for index
const getIndexMarkdown = (snippetIndex) => {

	const lines = [];

	// Loops main folders
	snippetIndex.forEach(subfolder => {

		lines.push(`## ${subfolder.title.toUpperCase()}`);

		// Loops subfolders
		subfolder.children.forEach(childFolder => {

			// Pushes our title
			lines.push(`### ${capitalizeFirstLetter(childFolder.title)}`);

			// Starts our links array
			const links = [];

			// Loops each link we have
			childFolder.children.forEach(snippet => {
				links.push(`[**${snippet.title}**](${encodeURI(snippet.link)}) - ${snippet.description}`);
			});

			// Pushes our links as a new line in our readme
			lines.push(links.join(`

---
\\
`));

		});

	});

	return lines.join(`
`);

}

// Gets our snipept index and generates readme file
(function() {
	
	const snippetIndex = [];
	
	// Parent directories
	const parentDirectories = fs.readdirSync('.', {
		withFileTypes: true
	}).filter(dirent => dirent.isDirectory() && ignoreFolders.indexOf(dirent.name) === -1);

	// Loops each parent directory
	parentDirectories.forEach(parent_1 => {

		// Level 1 object
		const thisSubFolder = {
			title: parent_1.name,
			children: []
		};
		
		// Read subdirectories of this folder
		const subDirectories = fs.readdirSync(path.resolve(parent_1.path, parent_1.name), {
			withFileTypes: true
		}).filter(dirent => dirent.isDirectory() && ignoreFolders.indexOf(dirent.name) === -1);
		
		// Loops each child directory
		subDirectories.forEach(parent_2 => {

			// Sets this child folder
			const thisChildFolder = {
				title: parent_2.name,
				children: []
			}
			
			// Gets the files in this chjild folder and loops them
			fs.readdirSync(path.resolve(parent_2.path, parent_2.name), {
				withFileTypes: true
			}).filter(dirent => dirent.isFile() && ignoreFolders.indexOf(dirent.name) === -1).forEach(snippet => {

				// Gets the title and description for this file and adds to the child folder
				const thisSnippetInfo = getSnippetInfo(snippet);

				if (!thisSnippetInfo) {
					return;
				}

				// Adds toi our child folder
				thisChildFolder.children.push({
					...thisSnippetInfo,
					link: `${ parent_1.name }/${ parent_2.name }/${ snippet.name }`
				});
				
			});

			// If the child folder has children - add to the subfolder
			if(thisChildFolder.children.length) {
				thisSubFolder.children.push(thisChildFolder);
			}
			
		});

		// If this subfolder has children - add to the snippet index
		if(thisSubFolder.children.length) {
			snippetIndex.push(thisSubFolder);
		}
		
	});

	// Generates markdown for directory
	const indexMarkDown = getIndexMarkdown(snippetIndex);

	// Gets the intructions block markdown
	const instructionsBlock = fs.readFileSync(path.resolve('./src', 'instructions-block.txt'), 'utf8');

	const readme = `# Snippet index
${indexMarkDown}
${instructionsBlock}`;

	// Writes the readme to the readme file
	fs.writeFileSync(path.resolve('.', 'README.md'), readme);

})();


// Gets the top most index
/*const getSnippetIndex = async () => {

	try {

		const parentDirectories = await fs.readdir('.', {
			withFileTypes: true
		});

	} catch(e) {
		console.error('Unable to genmerate index '+e.message);
	}

}

(async () => {

	const snippetIndex = await getSnippetIndex();

})();

// Function
(() => {

	// Reads parent directory
	fs.readdir('.', {
		withFileTypes: true
	}).then(parentDirectories => {

		const snippetsIndex = [];

		// Loops each directory
		parentDirectories.filter(dirent => dirent.isDirectory() && ignoreFolders.indexOf(dirent.name) === -1).forEach(parent_1 => {

			// Level 1 object
			const thisSubFolder = {
				title: parent_1.name,
				children: []
			};

			// Gets the subfolders for this subdirectory
			fs.readdir(path.resolve(parent_1.path, parent_1.name), {
				withFileTypes: true
			}).then(children => {

				// Loops our children directories
				children.filter(dirent => dirent.isDirectory() && ignoreFolders.indexOf(dirent.name) === -1).forEach(parent_2 => {

					// Sets this child folder
					const thisChildFolder = {
						title: parent_2.name,
						children: []
					}

					// Now we retrieve the files from this child folder
					fs.readdir(path.resolve(parent_2.path, parent_2.name), {
						withFileTypes: true
					}).then(files => {

						// Loops each file found
						files.filter(dirent => dirent.isFile()).forEach(snippet => {

							// Gets the title and description for this file and adds to the child folder
							getSnippetInfo(snippet).then(thisSnippetInfo => {

								// no data
								if (!thisSnippetInfo) {
									return;
								}

								console.log('a');

								// Adds this snippet to the child folder
								thisChildFolder.children = [
									...thisChildFolder.children,
									{
										...thisSnippetInfo,
										link: `${ parent_1.name }/${ parent_2.name }/${ snippet.name }`
									}
								];

							});

						});

					});

					console.log('b');

					// If this child folder has children - we add it to the suib folder
					if (thisChildFolder.children.length) {
						console.log('push here');
						thisSubFolder.children = [
							...thisSubFolder.children,
							thisChildFolder
						];
					}

				});

			});

			console.log('c');

			if(thisSubFolder.children.length) {
				snippetsIndex.push(thisSubFolder);
			}

		});

		console.log(snippetsIndex);

	});

})();*/



// Function
/*
(async () => {
	
	// Loops our root directory
	try {
		
		const parentDirectories = await fs.readdir('.', {
			withFileTypes: true
		})

		const snippetIndex = await new Promise((resolve, reject) => {

			let snippetIndexDirectory = [];

			// Loops each directory
			parentDirectories.filter(dirent => dirent.isDirectory() && ignoreFolders.indexOf(dirent.name) === -1).forEach(async parent_1 => {

				// Level 1 object
				const thisSubFolder = {
					title: parent_1.name,
					children: []
				};

				console.log('s');
				
				const subDirectories = await fs.readdir(path.resolve(parent_1.path, parent_1.name), {
					withFileTypes: true
				});

				// Gets the subfolders for this directory
				fs.readdir(path.resolve(parent_1.path, parent_1.name), {
					withFileTypes: true
				}).then(children => {

					console.log('e');
					
					// Loops our children directories
					children.filter(dirent => dirent.isDirectory() && ignoreFolders.indexOf(dirent.name) === -1).forEach(parent_2 => {

						// Sets this child folder
						const thisChildFolder = {
							title: parent_2.name,
							children: []
						}

						// Now we retrieve the files from this child folder
						fs.readdir(path.resolve(parent_2.path, parent_2.name), {
							withFileTypes: true
						}).then(files => {

							// Loops each file found
							files.filter(dirent => dirent.isFile()).forEach(async snippet => {

								// Gets the title and description for this file and adds to the child folder
								const thisSnippetInfo = await getSnippetInfo(snippet);

								if (!thisSnippetInfo) {
									return;
								}

								thisChildFolder.children = [
									...thisChildFolder.children,
									{
										...thisSnippetInfo,
										link: `${ parent_1.name }/${ parent_2.name }/${ snippet.name }`
									}
								];

							})

						});

						console.log('a');

						// If this child folder has children - we add it to the suib folder
						if (thisChildFolder.children.length) {
							thisSubFolder.children = [
								...thisSubFolder.children,
								thisChildFolder
							];
						}

					});

				});

				// If this subfolder has children - add it to the index
				if (thisSubFolder.children.length) {
					console.log('push');
					snippetIndexDirectory.push(thisSubFolder);
				}

			});

			resolve(snippetIndexDirectory);

		});

		console.log(snippetIndex);
		
	} catch (e) {
		
		console.error("Unable to generate index: "+e.message);
		
	}
	
	
})();*/