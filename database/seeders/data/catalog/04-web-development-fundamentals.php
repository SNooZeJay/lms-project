<?php

/**
 * Web Development Fundamentals.
 *
 * Front end first, then the point where the browser talks to a server. The
 * project module is deliberately open ended, because assembling the earlier
 * modules into one working site is the part students remember.
 */

return [

    'title' => 'Web Development Fundamentals',
    'slug' => 'web-development-fundamentals',
    'description' => 'Build a working website from the ground up. Covers how the web works, then HTML, CSS, responsive layout, JavaScript, the DOM, and fetching data from an API, ending with a small site that brings all of it together.',
    'learning_objectives' => "Explain what happens between typing a web address and seeing a page.\nWrite semantic HTML that a screen reader can follow.\nStyle a page with CSS, including layout that works on a phone.\nAdd behaviour with JavaScript and respond to user actions.\nRead and change the DOM.\nFetch data from an API and handle the request failing.\nAssemble a small multi page site.",
    'category' => 'Web Development',
    'level' => 'beginner',
    'course_type' => 'paid',
    'price_minor' => 150000,

    'modules' => [

        [
            'title' => 'How the Web Works',
            'description' => 'What happens in the second between Enter and a rendered page.',
            'lessons' => [
                [
                    'title' => 'From address to page',
                    'slug' => 'from-address-to-page',
                    'summary' => 'The request and response cycle, and the three parts involved.',
                    'estimated_minutes' => 12,
                    'content' => <<<'TEXT'
When you type a web address and press enter, the browser does not go and find
the page. It sends a request to a server, and the server sends back a response.

The request names a resource and asks for it. The response contains a status code
and the resource itself. The status code is the part worth learning first, because
it is the single most useful thing to look at when something does not work.

200 means success. 301 and 302 mean the page has moved. 400 means the request was
malformed. 401 means you are not logged in. 403 means you are logged in but not
allowed. 404 means the resource is not there. 500 means the server broke while
handling it.

Three parties are involved: you, the browser, and the server. Sometimes a fourth,
the DNS server, which turns a domain name into an address before anything else can
happen.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Status codes worth knowing',
                            'content' => "200 OK               the request succeeded\n301 Moved Permanently   the page has a new address\n302 Found            redirected for now, keep the old address\n400 Bad Request      the request was malformed\n401 Unauthorized      you need to sign in\n403 Forbidden         you are known, but not allowed\n404 Not Found         nothing at that address\n500 Server Error      the server failed while handling it",
                        ],
                    ],
                ],
                [
                    'title' => 'HTML, CSS, and JavaScript',
                    'slug' => 'the-three-languages',
                    'summary' => 'Structure, presentation, and behaviour.',
                    'estimated_minutes' => 11,
                    'content' => <<<'TEXT'
Three technologies build nearly every page you will see, and they have separate
jobs.

HTML is structure. It says what things are: a heading, a paragraph, a list, a
button. It does not say how they should look.

CSS is presentation. It says how they should look: the colour, the spacing, the
size, the position, and how the layout responds to the screen.

JavaScript is behaviour. It says what should happen when someone does something:
clicking a button, submitting a form, the page finishing loading.

Keeping the three separate is not a rule for its own sake. A page where the
structure is buried inside styling is hard to read for a screen reader, hard to
restyle, and hard for the next person to change. Separation is what makes each
part replaceable.
TEXT,
                    'materials' => [],
                ],
            ],
        ],

        [
            'title' => 'HTML',
            'description' => 'Structure that a browser and a screen reader both understand.',
            'lessons' => [
                [
                    'title' => 'Elements, attributes, and nesting',
                    'slug' => 'elements-attributes-nesting',
                    'summary' => 'The three things every tag is made of.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
An element is written as a tag with an opening and a closing pair. An attribute
is extra information on the opening tag. Elements nest, and the nesting is
meaningful rather than cosmetic.

    <a href="https://example.edu" title="Example site">
        Visit the example site
    </a>

Here `a` is the element, `href` and `title` are attributes, and the text between
the tags is the content.

Nesting is where beginners make the mistake that costs the most. Putting a `<div>`
inside a `<p>`, or a block element inside another block element in a way the
specification does not allow, causes the browser to repair the page silently. The
result usually still looks roughly right, which is why the mistake survives.

Learn the handful of elements you will use constantly: headings, paragraphs, lists,
links, images, and buttons. Using a heading for a heading and a styled paragraph
for a heading is not a shortcut, it removes information from the page.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'An element with attributes',
                            'content' => '<a href="https://example.edu" title="Example site">\n    Visit the example site\n</a>\n\n<!-- a is the element, href and title are attributes, the text is the content -->',
                        ],
                    ],
                ],
                [
                    'title' => 'Semantic HTML and accessibility',
                    'slug' => 'semantic-html',
                    'summary' => 'Choosing the element that means what you intend.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
Semantic HTML means using the element whose meaning matches what you intend, rather
than styling a generic element to look like it. A real button behaves like a
button: it can receive keyboard focus, it fires on both Enter and Space, and a
screen reader announces it as a button. A click handler on a div does none of that.

The same applies to headings, lists, labels, and images. An image needs alternative
text that says what the image conveys, not what it looks like. A form control
needs a real label tied to it with the `for` attribute, which is also what makes
clicking the label focus the field.

Headings are the part students get wrong most often, usually by choosing a level
for its size rather than its place in the outline. Heading levels should not skip:
an `h2` under an `h1` is fine, an `h4` directly under an `h1` is not.

The practical test: turn the stylesheet off. If the page still makes sense as a
document, the structure is doing its job.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Prefer the real element',
                            'content' => "Instead of:  <div class='button' onclick='save()'>Save</div>\nUse:          <button type='button' onclick='save()'>Save</button>\n\nThe real button is focusable, fires on Enter and Space, and is announced correctly.\nStyling a div produces all three failures at once.",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: HTML and semantics',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'Why prefer <button> over a <div> with a click handler?',
                                    'explanation' => 'A real button is focusable, responds to Enter and Space, and is announced as a button.',
                                    'options' => [
                                        ['text' => 'A real button is keyboard operable and announced correctly', 'correct' => true],
                                        ['text' => 'A div cannot receive a click event at all', 'correct' => false],
                                        ['text' => 'A button loads faster than a div', 'correct' => false],
                                        ['text' => 'A div cannot be styled', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'What does alternative text on an image need to convey?',
                                    'explanation' => 'It should describe what the image conveys, not what it looks like.',
                                    'options' => [
                                        ['text' => 'What the image conveys, not what it looks like', 'correct' => true],
                                        ['text' => 'The file name of the image', 'correct' => false],
                                        ['text' => 'A list of every colour in the image', 'correct' => false],
                                        ['text' => 'The dimensions of the image in pixels', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'CSS',
            'description' => 'Selecting, the box model, and layout.',
            'lessons' => [
                [
                    'title' => 'Selectors and the cascade',
                    'slug' => 'selectors-and-cascade',
                    'summary' => 'Choosing elements, and how conflicting rules are resolved.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
A CSS rule has two parts: a selector that says which elements it applies to, and a
declaration block that says what to change. Selectors range from an element name
to a class, an id, and combinations of them.

When two rules try to set the same property on the same element, the cascade
decides. Three things break the tie: importance, then specificity, then the order
the rules appear in. Specificity is the count of ids, then classes, then element
names in the selector.

This is why a selector with a long chain of tags loses to a single class. It is
also why fighting the cascade with `!important` is a bad habit: it wins, and you
have removed the only tool you had for working out why the page looks wrong.

Prefer one class per component and keep selectors short. A short selector is
easier to reason about, easier to reuse, and harder to accidentally break.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'Specificity in practice',
                            'content' => "/* Specificity 0,1,0: one class */\n.card { padding: 1rem; }\n\n/* Specificity 0,0,3: three elements. The class still wins. */\ndiv section article { padding: 2rem; }\n\n/* An id beats both, which is usually a sign the selector is too broad. */\n#sidebar { padding: 0; }",
                        ],
                    ],
                ],
                [
                    'title' => 'The box model',
                    'slug' => 'the-box-model',
                    'summary' => 'Content, padding, border, margin, and the sizing rule that surprises everyone.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
Every element on a page is a box made of four parts. Content is the text or image
itself. Padding is the space between the content and the border. The border is the
visible line. Margin is the space outside the border, which separates this element
from its neighbours.

Padding and border are inside the element, so they add to its size. Margin is
outside, so it pushes other elements away without changing this one's size.

That produces the rule that catches every beginner at least once. If you set a
width of 200 pixels and add padding of 20 pixels on each side, the element is 240
pixels wide, not 200. The fix is to tell the browser to include padding and border
in the width you declare.

Doing that once at the top of a stylesheet is the standard remedy, and it is worth
memorising rather than rediscovering.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'The sizing fix',
                            'content' => "* {\n    box-sizing: border-box;\n}\n\n/* With this, width now includes padding and border, so\n   width: 200px means 200px in total, not 240px. */",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Responsive Web Design',
            'description' => 'Layout that works from a small phone to a wide screen.',
            'lessons' => [
                [
                    'title' => 'Media queries and fluid units',
                    'slug' => 'media-queries-and-fluid-units',
                    'summary' => 'Adapting layout to the screen instead of to a device name.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
A media query applies rules only when the screen meets a condition, usually a
width. The important shift is that you respond to how much space there is, not to
what the device is called. A query written for a phone also works for a narrow
browser window on a laptop.

The other half of responsive design is using relative units. A percentage is
relative to the parent. The `rem` unit is relative to the root font size, and `vw`
and `vh` are relative to the viewport. A layout built from these scales without
needing a new rule at every size.

Two habits prevent most mobile problems. Never set a fixed width larger than the
screen, and make anything that can overflow scroll or shrink. Horizontal
scrolling on a phone is the most common avoidable failure on a page, and it is
almost always a fixed width somewhere.

Test by making the window narrow rather than only by using a device tool. If
something breaks, narrow the window further and find the element that refuses to
shrink.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'A fluid grid',
                            'content' => ".cards {\n    display: grid;\n    gap: 1rem;\n    grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));\n}\n\n/* No media query needed: the column count follows the space available. */",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: responsive layout',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'Why respond to available width rather than to a device name?',
                                    'explanation' => 'A narrow browser window on a laptop has the same problem as a phone, so the width is the useful condition.',
                                    'options' => [
                                        ['text' => 'A narrow window on a laptop has the same problem as a phone', 'correct' => true],
                                        ['text' => 'Device names are not available to CSS', 'correct' => false],
                                        ['text' => 'Width is always faster to read than height', 'correct' => false],
                                        ['text' => 'It reduces the file size', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'A page scrolls sideways on a phone. What is the most likely cause?',
                                    'explanation' => 'A fixed width wider than the screen, or an element that cannot shrink.',
                                    'options' => [
                                        ['text' => 'A fixed width wider than the screen, or an element that cannot shrink', 'correct' => true],
                                        ['text' => 'Too many colours in the stylesheet', 'correct' => false],
                                        ['text' => 'The page is using semantic HTML', 'correct' => false],
                                        ['text' => 'A missing viewport meta tag only', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'JavaScript Fundamentals',
            'description' => 'Values, functions, and how a page responds.',
            'lessons' => [
                [
                    'title' => 'Variables, types, and functions',
                    'slug' => 'js-variables-and-functions',
                    'summary' => 'The core of the language, and close enough to Python to be familiar.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
If you have written any Python, JavaScript will feel partly familiar and partly
misleading. The variable declarations differ, and there are two of them to choose
between. `const` declares a name that is not reassigned, and `let` declares one
that is. Use `const` by default and reach for `let` when you genuinely need to
reassign.

The types are similar: strings, numbers, booleans, arrays, objects, null, and
undefined. Two differences matter. JavaScript has no separate integer and float
type, and a missing value is `undefined` while an explicitly empty one is `null`.

Functions work much as they do in Python, and the same advice applies. Take
parameters, return a result, and do one thing. The arrow syntax is shorter and is
what you will see most often:

    const total = (scores) => scores.reduce((sum, s) => sum + s, 0);

The single biggest difference from Python is the type system. JavaScript will
happily add a number to a piece of text and give you a surprising result, so
checking what you have got is a normal part of the work rather than an exception.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'A familiar function',
                            'content' => "// const by default, let when you must reassign\nconst limit = 3;\nlet attempts = 0;\n\nconst remaining = (max) => max - attempts;\n\nconsole.log(remaining(limit));   // 3",
                        ],
                    ],
                ],
                [
                    'title' => 'Responding to the user',
                    'slug' => 'responding-to-the-user',
                    'summary' => 'Events, and the difference between a value and a reference.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
An event is something that happened: a click, a key press, a form submission, a
page finishing loading. You register a function to run when it happens, and that
function usually changes something on the page.

The subtlety worth understanding early is that arrays and objects are passed by
reference, while numbers and strings are passed by value. If a function receives an
array and removes an item from it, the caller sees the change. If a function
receives a number and doubles it, the caller's number is untouched.

That is why functions that should not modify their input usually take a copy first.
It also explains a common surprise: sorting an array in place changes the original,
whereas returning a sorted copy leaves it alone.

When a form is submitted, take the values out before the page reloads. Without
that, the work is lost the moment the page refreshes.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'By reference, which surprises people',
                            'content' => "const scores = [88, 92, 79];\n\nconst addBonus = (list) => list.push(100);  // modifies the original\nconst sortedCopy = (list) => [...list].sort(); // leaves it alone\n\naddBonus(scores);\nconsole.log(scores.length);   // 4, the original changed\n\nconsole.log(sortedCopy(scores)); // a new sorted array\nconsole.log(scores);             // the original, still 88, 92, 79, 100",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'DOM and Events',
            'description' => 'Finding elements on the page and changing them.',
            'lessons' => [
                [
                    'title' => 'Finding and changing elements',
                    'slug' => 'finding-and-changing-elements',
                    'summary' => 'Selecting, creating, and updating the page from code.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
The DOM is the browser's live representation of the page. JavaScript can find an
element in it, read it, change it, add new ones, and remove old ones.

Finding elements is best done by id or by a data attribute, and worst by position.
`document.querySelector('#total')` says what you mean. `nth-child` says where it
happens to be today, and breaks the moment someone adds a row above it.

Prefer `textContent` to `innerHTML` when you are putting text in. It is faster and
it cannot be used to inject markup, so a value that came from a user cannot become
executable. `innerHTML` is for markup you built yourself, not for values.

The DOM is rebuilt whenever something changes, so read an element when you need it
rather than holding on to it. A stored reference can point at a node that has since
been replaced.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'Changing a page',
                            'content' => "const output = document.querySelector('#total');\noutput.textContent = '42';   // safe for any value\n\nconst list = document.querySelector('#results');\nconst row = document.createElement('li');\nrow.textContent = item;       // textContent, not innerHTML\nlist.append(row);",
                        ],
                    ],
                ],
                [
                    'title' => 'Listening for events',
                    'slug' => 'listening-for-events',
                    'summary' => 'Adding behaviour, and keeping the page usable without it.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
`addEventListener` registers a function to run when something happens on an element.
It is the main way behaviour is attached to a page.

```javascript
form.addEventListener('submit', (event) => {
    event.preventDefault();
    save();
});
```

Calling `preventDefault` on a submit stops the page reloading. Without it, the work
in `save` happens and then the browser throws the result away by navigating.

The design principle that matters here is progressive enhancement. The page should
work before the script runs, and the script should make it better rather than make
it exist. A form with a real `action` attribute works without JavaScript; the
script can then take over the submission to avoid a reload.

That is not a workaround for old browsers. It is what makes a page usable with a
slow connection, with a keyboard, and when something fails.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'Progressive enhancement',
                            'content' => "<!-- works with no JavaScript at all -->\n<form id='search' action='/courses' method='get'>\n    <input name='q'>\n    <button>Search</button>\n</form>\n\n<script>\n  // enhances it once the page has loaded\n  document.querySelector('#search').addEventListener('submit', (e) => {\n    e.preventDefault();\n    searchWithoutReloading();\n  });\n</script>",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Working with APIs',
            'description' => 'Getting data from a server, and handling it failing.',
            'lessons' => [
                [
                    'title' => 'Fetching data',
                    'slug' => 'fetching-data',
                    'summary' => 'Requesting a resource and reading the response.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
`fetch` requests a resource and gives back a promise. A promise represents a result
that will arrive later, and you handle it with `then` or `await`.

```javascript
const response = await fetch('/api/courses');
const courses = await response.json();
```

Two things catch people. A request that fails and a request that returns an error
are different: `fetch` only rejects when the network fails, not when the server
returns 404 or 500. So check `response.ok` separately. And the body has to be read
separately from the response, which is what `response.json()` does.

Write the failure path deliberately. Show the user something rather than leaving a
blank area, and never assume the data is shaped the way you expected, because it
may come from somewhere you do not control.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'Checking for failure',
                            'content' => "const response = await fetch('/api/courses');\n\nif (!response.ok) {\n    showMessage('Courses could not be loaded. Try again.');\n    return;\n}\n\nconst courses = await response.json();\nrender(courses);",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: fetching data',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'What must you check after a fetch returns?',
                                    'explanation' => 'fetch only rejects on a network failure, so an error status still resolves normally and has to be checked.',
                                    'options' => [
                                        ['text' => 'response.ok, because fetch only rejects on a network failure', 'correct' => true],
                                        ['text' => 'Nothing, because fetch throws on every error', 'correct' => false],
                                        ['text' => 'The length of the response text', 'correct' => false],
                                        ['text' => 'The browser version', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'Why prefer textContent over innerHTML for a value from a user?',
                                    'explanation' => 'textContent cannot be used to inject markup, so the value cannot become executable.',
                                    'options' => [
                                        ['text' => 'textContent cannot inject markup, so the value stays text', 'correct' => true],
                                        ['text' => 'innerHTML does not work on a value', 'correct' => false],
                                        ['text' => 'textContent is the only method that works in a browser', 'correct' => false],
                                        ['text' => 'innerHTML is always slower', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Final Website Project',
            'description' => 'Assembling the modules into one working site.',
            'lessons' => [
                [
                    'title' => 'Planning and building the site',
                    'slug' => 'building-the-site',
                    'summary' => 'Scope it small, build it in order, and check each part.',
                    'estimated_minutes' => 15,
                    'content' => <<<'TEXT'
The project is a small multi page site. Two or three pages is enough. The point is
to build something that genuinely works, not something with every feature.

Plan it before you write it. List the pages, what each one shows, and what the user
can do on it. Then decide where the data comes from, which is usually a small JSON
file at first. That is enough to build the whole thing against, and you can move to
a real API afterwards.

Build in an order where each step is testable. Structure first, with real headings
and real links, so you can walk the site before it looks like anything. Then
styling. Then behaviour. Then data. A student who styles first spends the whole
afternoon fighting a layout that is going to be rewritten anyway.

Finish the parts that matter and leave the rest out. A site with three working
pages and no home page carousel is a better project than one with six pages where
nothing works.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Build order that works',
                            'content' => "1. Plan: pages, purpose of each, source of the data.\n2. Structure: semantic HTML with real headings and working links.\n3. Data: a small JSON file, loaded and rendered.\n4. Styling: layout, then type and colour.\n5. Behaviour: only what the user actually needs.\n6. Responsive: check at a narrow width.\n7. Accessibility: keyboard, focus, contrast, alternative text.",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: course review',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'Why build the structure before the styling?',
                                    'explanation' => 'Structure can be walked and tested before it looks like anything, and styling early usually gets rewritten.',
                                    'options' => [
                                        ['text' => 'The site can be walked and tested before it looks like anything', 'correct' => true],
                                        ['text' => 'CSS does not work without HTML being finished first', 'correct' => false],
                                        ['text' => 'It makes the page load faster', 'correct' => false],
                                        ['text' => 'It avoids needing JavaScript', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'What does a media query respond to?',
                                    'explanation' => 'A condition on the display, usually its width, rather than the identity of the device.',
                                    'options' => [
                                        ['text' => 'A condition on the display, usually its width', 'correct' => true],
                                        ['text' => 'The make and model of the device', 'correct' => false],
                                        ['text' => 'The browser version string', 'correct' => false],
                                        ['text' => 'The operating system only', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

    ],

];
