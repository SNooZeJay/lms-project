<?php

/**
 * Computer Fundamentals and Digital Literacy.
 *
 * The hands on companion to the first course. Less about what a computer is and
 * more about operating one, and about the everyday digital judgement that comes
 * with it.
 */

return [

    'title' => 'Computer Fundamentals and Digital Literacy',
    'slug' => 'computer-fundamentals-and-digital-literacy',
    'description' => 'Hands on operation of a computer and the everyday digital skills that depend on it. Covers the desktop, files, both major operating system families, browsing, email, and the office tools a first year student actually needs.',
    'learning_objectives' => "Navigate a desktop and use the window and file management tools confidently.\nOrganise files so they can be found again.\nUse a web browser safely and judge whether a site is trustworthy.\nSend and receive email, and recognise a spoofed message.\nProduce a document, a spreadsheet, and a presentation to an acceptable standard.",
    'category' => 'Digital Literacy',
    'level' => 'beginner',
    'course_type' => 'free',
    'price_minor' => 0,

    'modules' => [

        [
            'title' => 'Computer Basics',
            'description' => 'Using the pointer, the keyboard, and the window.',
            'lessons' => [
                [
                    'title' => 'Using the pointer and keyboard',
                    'slug' => 'pointer-and-keyboard',
                    'summary' => 'Clicking, double clicking, right clicking, selecting, and the keys that matter.',
                    'estimated_minutes' => 10,
                    'content' => <<<'TEXT'
Three pointer actions cover most of what you will ever do. A single click selects
something. A double click opens it. A right click opens a menu of the actions
available for whatever is under the pointer. That last one is the most useful and
the most often ignored: almost every application offers a context menu, and it is
usually faster than hunting through the menus at the top of the window.

On the keyboard, learn the shortcut for copy, paste, and cut first, then undo.
Undo is the habit that separates someone comfortable with a computer from someone
who avoids it. Anything in these applications can be undone, so the cost of trying
something is close to zero.

A useful distinction to build early: selecting is not the same as opening. If a
file opens when you expected it to be selected, you have probably double clicked by
accident.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Shortcuts worth memorising',
                            'content' => "Copy: Control or Command + C\nPaste: Control or Command + V\nCut: Control or Command + X\nUndo: Control or Command + Z\nSelect all: Control or Command + A\nSwitch windows: Alt + Tab",
                        ],
                    ],
                ],
                [
                    'title' => 'Windows and applications',
                    'slug' => 'windows-and-applications',
                    'summary' => 'Moving, resizing, minimising, and closing without losing work.',
                    'estimated_minutes' => 10,
                    'content' => <<<'TEXT'
An application runs inside a window. Once you can move, resize, minimise and close
a window, you can work with almost any program.

The two habits that prevent most lost work are worth stating plainly. Close with
the close button rather than the small cross in the corner of every program,
because it is the one that saves your work. And when an application asks whether
to save before closing, the answer is almost always yes unless you meant to
discard the change.

Minimising sends a program out of the way without ending it. Closing ends it.
That difference is the reason an application can be closed by mistake but rarely
minimised by mistake.
TEXT,
                    'materials' => [],
                ],
            ],
        ],

        [
            'title' => 'Windows and Linux Fundamentals',
            'description' => 'The two operating system families you will meet.',
            'lessons' => [
                [
                    'title' => 'Windows and Linux side by side',
                    'slug' => 'windows-and-linux',
                    'summary' => 'Same ideas, different interface. And why Linux matters to a computing degree.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
Windows and Linux look different but are doing the same job. Both manage files,
running programs, users, and hardware. Once you understand that, switching between
them is mostly learning new button positions rather than new concepts.

The file systems differ, and that difference causes real practical trouble. A path
written for Windows, with a drive letter and backslashes, will not work on Linux. A
path written for Linux will not work on Windows. Anything you write that has to
run on both should avoid hard coded paths altogether.

Linux matters to a computing degree for three reasons. Most web servers run on
it, so you will meet it if you deploy anything. Most development tooling assumes
it or has a first class version for it. And it is free, which makes it practical
to experiment on a machine you cannot reformat.

You do not need to be an expert. Being comfortable opening a terminal and moving
around a filesystem is enough to start.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'The path problem',
                            'content' => "Windows: C:\\Users\\name\\Documents\nLinux: /home/name/Documents\n\nHard coded paths written for one system will break on the other. Ask the operating system for the location instead of assuming it.",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: operating systems and files',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'Why does a hard coded file path written for Windows fail on Linux?',
                                    'explanation' => 'The two systems use different path syntax, with a drive letter on Windows and a rooted path on Linux.',
                                    'options' => [
                                        ['text' => 'The two systems use different path syntax', 'correct' => true],
                                        ['text' => 'Linux cannot read files from another system', 'correct' => false],
                                        ['text' => 'Windows paths are longer and are rejected for being too long', 'correct' => false],
                                        ['text' => 'Linux stores files in a different order', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'Which of these is a practical reason to learn some Linux in a computing degree?',
                                    'explanation' => 'Most web servers run on Linux, and development tooling commonly assumes it.',
                                    'options' => [
                                        ['text' => 'Most web servers run on Linux and development tools commonly assume it', 'correct' => true],
                                        ['text' => 'Linux is the only operating system that supports networking', 'correct' => false],
                                        ['text' => 'Linux is required to study for the degree', 'correct' => false],
                                        ['text' => 'Windows cannot be used by students', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Files and Folders',
            'description' => 'The single habit that saves the most time later.',
            'lessons' => [
                [
                    'title' => 'Organising files so you can find them again',
                    'slug' => 'organising-files',
                    'summary' => 'Folders, naming, search, and the difference between a file and a shortcut.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
A file system is a tree. Folders contain files and other folders, and the whole
structure starts from a root. Knowing that it is a tree, and not a list, explains
why moving a folder moves everything inside it, and why deleting something deep in
the tree can leave an empty branch behind.

Name files so that you can tell them apart later. Dates, a clear subject, and a
consistent pattern beat anything clever. A name like
Quiz2_Final_v3_REALLYFINAL.docx helps nobody, including you next week.

Learn to search as well as to browse. Search by name is fast on every modern
system, and search by content is available in most. Someone who searches will
always beat someone who scrolls.

Finally, be able to tell a file from a shortcut. A shortcut is a pointer to
something stored elsewhere; deleting it leaves the original alone. Deleting the
original does not delete the shortcut, which then points at nothing and produces
an error when opened.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'File extensions worth recognising',
                            'content' => ".txt   plain text\n.pdf   fixed layout document\n.docx  word processor document\n.xlsx  spreadsheet\n.pptx  presentation\n.jpg / .png  images\n.zip   compressed archive\n.exe   Windows program\n.py    Python source",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Internet and Web Browsing',
            'description' => 'Using a browser well and judging what you find.',
            'lessons' => [
                [
                    'title' => 'Using a browser and judging a site',
                    'slug' => 'using-a-browser',
                    'summary' => 'Tabs, downloads, and the three things that indicate a trustworthy site.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
A browser is a program that retrieves pages and runs the code they contain. That
second part is worth noticing, because it is why a page can ask your computer to do
things, and why a browser is a frequent target for attack.

Three checks indicate whether a site deserves your trust. Look at the address bar
and read the domain properly, because lookalike domains exist and the difference
is often a single character. Check whether the connection is encrypted, shown as
a lock in the address bar, which means data in transit cannot be read by anyone
between you and the site. And ask who is behind the page: a contact address, a
real author, and a stated organisation are all signs, and their absence is itself
information.

Downloads deserve the same suspicion as email attachments. If a file arrives that
you did not go looking for, treat it the way you would treat an unexpected
attachment in a message.
TEXT,
                    'materials' => [
                        [
                            'type' => 'external_link',
                            'title' => 'Reference: how to read a web address',
                            'url' => 'https://consumer.ftc.gov/articles/how-spot-avoid-phishing-scams',
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: browsing safely',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'A site asks you to enter your school password to "verify" your student number. What should you do?',
                                    'explanation' => 'A legitimate service does not need your password to check an identity, and this is a standard phishing pattern.',
                                    'options' => [
                                        ['text' => 'Close the page and reach the service through its official address instead', 'correct' => true],
                                        ['text' => 'Enter it, since the page uses a lock in the address bar', 'correct' => false],
                                        ['text' => 'Enter it but change the password afterwards', 'correct' => false],
                                        ['text' => 'Enter it to see what the site does next', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'What does the lock symbol in a browser address bar tell you?',
                                    'explanation' => 'It indicates the connection is encrypted in transit. It says nothing about whether the site itself is honest.',
                                    'options' => [
                                        ['text' => 'The connection is encrypted, but it does not mean the site is trustworthy', 'correct' => true],
                                        ['text' => 'The site has been verified as safe by the browser', 'correct' => false],
                                        ['text' => 'The site cannot collect any data', 'correct' => false],
                                        ['text' => 'The site uses a secure payment system', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Email and Online Communication',
            'description' => 'Writing email that gets answered, and spotting a forged one.',
            'lessons' => [
                [
                    'title' => 'Writing email that gets answered',
                    'slug' => 'writing-email',
                    'summary' => 'Subject lines, being specific, and replying in a thread.',
                    'estimated_minutes' => 11,
                    'content' => <<<'TEXT'
Most unanswered email is badly aimed rather than badly written. A message that
says "please advise about the project" gives the reader nothing to act on.

Write a subject line that states the request, keep the message to one topic, and
say what you need and by when. If you are asking a question, include the context
that would otherwise make the reader ask you for it.

Reply within the existing thread rather than starting a new message. Most people
organise their inbox by conversation, and a new message with the same subject
splits the history and makes the answer harder to find.

Be careful with attachments. State the attachment name in the body, because people
read the body first, and it makes a missing file obvious.
TEXT,
                    'materials' => [],
                ],
                [
                    'title' => 'Spotting a forged message',
                    'slug' => 'spotting-a-forged-message',
                    'summary' => 'Display name against real address, and urgency.',
                    'estimated_minutes' => 12,
                    'content' => <<<'TEXT'
A forged message usually has one tell that is easy to check and easy to miss. The
display name is what the sender chose to show, and it can be anything. The actual
address is what you should read, and it is often not the same as the person or
organisation the display name claims.

Check the part after the at sign. A message claiming to be from your registrar may
come from an address at an unrelated domain, and the display name will hide that
difference completely.

The second tell is emotional pressure. Requests for gift cards, urgent payment
changes, and threats about a suspended account all rely on you acting before you
read carefully. There is never a good reason to send gift cards by email, and
there is rarely a genuine deadline measured in minutes.

If a message worries you, do not reply to it. Contact the person through a channel
you already trust, using a number or address you looked up yourself.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'The thirty second check',
                            'content' => "1. Read the full address, not the display name.\n2. Look at the domain after the at sign. Is it the organisation it claims to be?\n3. Does the message create urgency, threaten suspension, or ask for payment or gift cards?\n\nIf any answer is wrong, do not reply. Contact the person through a channel you already trust.",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Productivity Tools',
            'description' => 'Documents, spreadsheets, and presentations to a usable standard.',
            'lessons' => [
                [
                    'title' => 'Documents that other people can read',
                    'slug' => 'readable-documents',
                    'summary' => 'Structure, plain language, and formatting that survives being passed on.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
A document that communicates is built in a predictable order: an opening that
says what the piece is about, headings that let a reader find the part they want,
and a conclusion that says what it means. Most weak documents are simply
unstructured, and a reader has to guess the order themselves.

Write in plain language. Short sentences are easier to follow and easier to
correct. Prefer the everyday word over the impressive one, and cut any phrase that
could be deleted without losing meaning.

Formatting carries meaning when it is consistent. One heading style, one body
style, and one spacing rule used throughout. Inconsistent formatting does not just
look untidy, it removes the visual cues a reader would normally use to understand
the structure of the piece.

Finally, export to a format other people can open. A document saved only in one
vendor's format becomes unreadable the moment that software is missing, and PDF is
usually the right choice for anything that has to be submitted.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Checklist before submitting a document',
                            'content' => "Does the first paragraph say what the document is about?\nAre there headings a reader can navigate by?\nIs there a conclusion?\nIs the font and spacing consistent throughout?\nHave you spelled it once, by reading it aloud?\nHave you exported a PDF for submission?",
                        ],
                    ],
                ],
                [
                    'title' => 'Spreadsheets that do not lie',
                    'slug' => 'spreadsheets-that-do-not-lie',
                    'summary' => 'One value per cell, and formulas you can check.',
                    'estimated_minutes' => 15,
                    'content' => <<<'TEXT'
Most errors in spreadsheets come from two habits: putting more than one thing in a
cell, and typing a number where a formula belongs.

A cell should hold one value. A date, a name, and an amount in one cell is
readable to a person and unusable to a formula. Split them, and the column
becomes something you can sort, filter, and add up.

Write calculations as formulas rather than typed results. A total that was typed is
correct today and wrong the moment a row is inserted. A total that was calculated
updates itself, and you can see how it was arrived at.

Finally, keep the raw data separate from the working. A sheet that mixes imported
figures, working notes, and the final summary becomes impossible to check. If a
number matters, it should be traceable to where it came from.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Spreadsheet habits',
                            'content' => "One value per cell.\nFormulas for calculations, never typed results.\nRaw data kept apart from the working sheet.\nConsistent column formats, especially dates and currency.\nCheck a total against a second method before relying on it.",
                        ],
                    ],
                ],
                [
                    'title' => 'Presentations that hold attention',
                    'slug' => 'presentations',
                    'summary' => 'One idea per slide, and saying it in your own words.',
                    'estimated_minutes' => 12,
                    'content' => <<<'TEXT'
A presentation is not a document that has been cut into slides. The audience
cannot read and listen at the same time, so the slides carry the structure and you
carry the explanation.

Put one idea on each slide. If you need two, it is usually two slides. Keep the
wording on the slide to a headline plus a short supporting line, and resist the
temptation to read a paragraph aloud, because the audience reads faster than you
speak and will arrive before you do.

Rehearse the opening. Most presentations are decided in the first thirty seconds,
and knowing your first two sentences removes the failure mode where someone starts
reading the slide instead of listening.

Check that the file opens on the machine it will be shown from. Exporting a PDF is
the reliable answer, and it also stops the fonts changing on someone else's
computer.
TEXT,
                    'materials' => [],
                ],
            ],
        ],

    ],

];
