<?php

/**
 * Introduction to Information Technology.
 *
 * A first course in computing for a student who has not programmed before. The
 * pitch is deliberately wide: a first year subject answers "what is this field"
 * before it answers anything narrower.
 */

return [

    'title' => 'Introduction to Information Technology',
    'slug' => 'introduction-to-information-technology',
    'description' => 'A first course in computing for students who have not programmed before. It covers what information technology is, how a computer is built, how software and operating systems work, how networks and the internet fit together, how security protects all of it, and what the career options look like.',
    'learning_objectives' => "Explain what information technology is and why it matters to a computing degree.\nDescribe the main parts of a computer and what each one does.\nDistinguish between system software and application software.\nExplain how the internet differs from a local network.\nDescribe common cyber threats and the habits that reduce them.\nIdentify entry level roles in the information technology field.",
    'category' => 'Information Technology',
    'level' => 'beginner',
    'course_type' => 'free',
    'price_minor' => 0,

    'modules' => [

        [
            'title' => 'What is IT?',
            'description' => 'The vocabulary the rest of the course depends on.',
            'lessons' => [
                [
                    'title' => 'Information technology defined',
                    'slug' => 'information-technology-defined',
                    'summary' => 'What the term covers, and what it deliberately leaves out.',
                    'estimated_minutes' => 12,
                    'content' => <<<'TEXT'
Information technology is the use of computers to collect, store, process, and
present information. The term covers the hardware, the software, the networks that
connect them, and the people who design and maintain them. In practice an IT
department is less interested in computers as objects than in the systems built on
top of them: a student records system, a hospital billing system, a bank ledger,
or a delivery tracker.

That second framing matters. A single computer is a tool. A system that several
people depend on has requirements: it has to stay available, it has to keep its
records correct, and it has to protect the records it holds. Those requirements
are where most of the work in IT actually sits.

The term is often confused with information systems, and the difference is worth
holding on to. Information technology is the equipment and the software. An
information system is the whole arrangement: the people, the procedures, the data,
and the technology together. Buying a laptop is information technology. Running a
registration system that three offices depend on is an information system.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Key terms in this lesson',
                            'content' => "Data: raw facts with no meaning yet.\nInformation: data that has been given context.\nSystem: a set of parts that work together toward a purpose.\nHardware: the physical parts of a computer.\nSoftware: the instructions that run on that hardware.",
                        ],
                        [
                            'type' => 'video_link',
                            'title' => 'Supplementary lecture: data, information, and knowledge',
                            'url' => 'https://www.youtube.com/watch?v=1T0kXJi8jBs',
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: information technology and information systems',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'Which statement best separates an information system from information technology?',
                                    'explanation' => 'The technology is the equipment and software. The system adds the people, procedures, and data around it.',
                                    'options' => [
                                        ['text' => 'An information system includes the people and procedures that use the technology', 'correct' => true],
                                        ['text' => 'An information system is cheaper to build than the technology it runs on', 'correct' => false],
                                        ['text' => 'Information technology is the study, while an information system is the equipment', 'correct' => false],
                                        ['text' => 'They are two names for the same thing', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'Which of these is an example of data rather than information?',
                                    'explanation' => 'A raw number has no meaning until something gives it context.',
                                    'options' => [
                                        ['text' => '32', 'correct' => true],
                                        ['text' => 'The student scored 32 out of 100 on the quiz', 'correct' => false],
                                        ['text' => 'A passing grade in the introductory computing subject', 'correct' => false],
                                        ['text' => 'The class average after the first week', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Why computing matters in professional work',
                    'slug' => 'why-computing-matters',
                    'summary' => 'Where information technology shows up outside the computing degree.',
                    'estimated_minutes' => 10,
                    'content' => <<<'TEXT'
Almost every field now keeps part of its work in a computer system, and the useful
skill is rarely the ability to code. It is the ability to notice where a process
depends on information, decide what the system should record, and check whether
it recorded it correctly.

A nurse reading a medication record, an accountant reconciling a ledger, and a
logistics planner watching a delivery route are all reading data produced by a
system. Each needs to know what the number means, how current it is, and what
happens when it is wrong. That is a computing skill applied outside computing.

For a student this is worth taking seriously early. It is easier to ask good
questions about a system when you already know roughly how it was built. The rest
of this course gives you that baseline.
TEXT,
                    'materials' => [],
                ],
            ],
        ],

        [
            'title' => 'Computer Hardware',
            'description' => 'The physical parts of a computer and what each one contributes.',
            'lessons' => [
                [
                    'title' => 'The parts of a computer',
                    'slug' => 'the-parts-of-a-computer',
                    'summary' => 'Input, processing, memory, storage, and output.',
                    'estimated_minutes' => 15,
                    'content' => <<<'TEXT'
Every computer, from a phone to a server, does the same five things. It takes in
data, processes it, holds it while it works, stores it for later, and gives the
result back out. Once you can place a component into one of those five roles, the
hardware stops being a list of names.

Input devices include the keyboard, mouse, touchscreen, microphone, camera, and
scanner. The processing unit, called the central processing unit or CPU, is where
instructions are carried out. Memory is the working space: random access memory
holds the programs currently running, and it is fast but temporary. Storage is the
permanent record, slower but able to survive a power cut. Output devices include
the screen, the printer, and the speakers.

The distinction between memory and storage causes the most confusion at the start
of a computing course. Moving a file from a solid state drive into random access
memory makes it faster to work with, but the copy in memory disappears when the
power goes off. Nothing is lost, because the original is still on the drive.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Components by role',
                            'content' => "Input: keyboard, mouse, scanner, microphone, camera\nProcess: central processing unit\nWorking memory: random access memory\nPermanent storage: solid state drive, hard disk\nOutput: monitor, printer, speakers",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: computer components',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'Which component is responsible for carrying out instructions?',
                                    'explanation' => 'The processor executes the instructions of whatever program is running.',
                                    'options' => [
                                        ['text' => 'The central processing unit', 'correct' => true],
                                        ['text' => 'Random access memory', 'correct' => false],
                                        ['text' => 'A solid state drive', 'correct' => false],
                                        ['text' => 'The monitor', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'What happens to a file that has been copied from storage into random access memory?',
                                    'explanation' => 'The copy in memory is lost when power is lost, but the original remains on the drive.',
                                    'options' => [
                                        ['text' => 'The copy in memory is lost on power off, while the original stays on the drive', 'correct' => true],
                                        ['text' => 'Both copies are lost on power off', 'correct' => false],
                                        ['text' => 'The original is deleted when the copy is made', 'correct' => false],
                                        ['text' => 'The file is moved permanently into faster storage', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Inside the processor and clock speed',
                    'slug' => 'inside-the-processor',
                    'summary' => 'What clock speed measures, and why it is not the whole story.',
                    'estimated_minutes' => 12,
                    'content' => <<<'TEXT'
The clock speed of a processor, measured in gigahertz, tells you how many cycles
the chip performs each second. It is the headline number on most specifications,
and it is a real constraint, but it explains far less than marketing suggests.

Performance is decided by the whole system. A very fast processor paired with a
slow solid state drive will still feel slow in daily use, because most of the
waiting in a modern computer is spent fetching data from storage. Two processors
with the same clock speed can behave very differently depending on core count,
cache size, and architecture.

This is the first place a computing student meets an idea that recurs throughout
the degree: a specification tells you what a component can do on its own, not how
fast the finished system will feel. The same reasoning applies to comparing two
laptops, two phones, or two database servers.
TEXT,
                    'materials' => [],
                ],
            ],
        ],

        [
            'title' => 'Software and Operating Systems',
            'description' => 'What software is, and the job the operating system does.',
            'lessons' => [
                [
                    'title' => 'System software and application software',
                    'slug' => 'system-and-application-software',
                    'summary' => 'The two broad categories and why the split matters.',
                    'estimated_minutes' => 12,
                    'content' => <<<'TEXT'
System software manages the machine itself. The operating system is the most
important piece of it, but system software also includes device drivers, which let
the operating system talk to a specific piece of hardware, and utilities, which
perform maintenance tasks such as backup or disk cleanup.

Application software is built to do a job for a person: a word processor, a
spreadsheet, a browser, a games program. The distinction is about audience. An
application is written for whoever uses it. System software is written for the
programs that run on the machine.

Understanding the split explains a lot of everyday frustration. When an
application behaves oddly on a particular machine, the fault is often a driver
rather than the application. That is a system software problem wearing an
application's clothes.
TEXT,
                    'materials' => [],
                ],
                [
                    'title' => 'What the operating system does',
                    'slug' => 'what-the-operating-system-does',
                    'summary' => 'Process management, memory management, storage, and security.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
An operating system sits between the programs a person runs and the hardware
beneath. Four of its jobs are worth naming precisely, because they reappear in
almost every later subject.

Process management means several programs can appear to run at the same time. The
operating system gives each one a slice of the processor's time and keeps them
from interfering. Memory management means each program is given its own space, so
one program cannot read another's data by accident. Storage management turns files
into something the hardware can hold, and back again. Security management decides
who is allowed to do what, and records it.

When you have used a slow computer, the most common reason is not that the
processor is too small. It is that too many programs are competing for it. That
is a process management outcome, and it is why closing background applications
actually helps.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'The four jobs to remember',
                            'content' => "1. Process management: sharing processor time between running programs.\n2. Memory management: giving each program its own protected space.\n3. Storage management: turning files into something hardware can hold.\n4. Security management: deciding and recording who may do what.",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Networks and Internet Basics',
            'description' => 'How machines talk to each other, and how the internet is built.',
            'lessons' => [
                [
                    'title' => 'What a network is',
                    'slug' => 'what-a-network-is',
                    'summary' => 'Clients, servers, and the difference between local and wide area networks.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
A network is two or more devices connected well enough to exchange data. The two
roles are almost always present. The client is the device making a request, such
as your laptop loading a web page. The server is the device answering it, holding
the data or the service being asked for. One machine can be both at different
times, which is why the terms describe a relationship rather than a device.

A local area network stays within one building or site, such as the computers in
a computer laboratory joined by a switch. A wide area network spans distance and
is normally operated by a telecommunications provider. The internet is the largest
wide area network in use, and it is a network of networks: no single organisation
owns it or controls its whole path.

The practical consequence for a student is that a local network failure and an
internet failure look different from the user's seat. If one machine cannot reach
the server, the problem is close. If every machine cannot, the problem is further
out, and someone other than you may have to fix it.
TEXT,
                    'materials' => [],
                ],
                [
                    'title' => 'IP addresses and the web',
                    'slug' => 'ip-addresses-and-the-web',
                    'summary' => 'Addressing, and the difference between the internet and the web.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
Every device on a network has an address, written as an internet protocol address.
The version you will meet most often today is written as four numbers, such as
192.168.1.10, and the first part often indicates a private network that is not
reachable from outside.

It is worth being precise about two words that people use interchangeably. The
internet is the infrastructure: the networks, cables, routers, and agreements that
move data between machines. The web is one service that runs on top of it, the
system of pages and links reached through a browser. Email is another service on
the same infrastructure, which is a good reminder that the web is not the
internet.

Domain names exist because people remember names better than numbers. When you
type a domain name, a lookup turns it into the address of a server, and the
connection is then made to that address rather than to the name.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Internet or web?',
                            'content' => "Internet: the network infrastructure that moves data between machines.\nWeb: one service on that infrastructure, reached through a browser.\nEmail: another service on the same infrastructure.\n\nThe web is not the internet, though the words are often used as if they were.",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Cybersecurity Fundamentals',
            'description' => 'The threats that matter day to day and the habits that address them.',
            'lessons' => [
                [
                    'title' => 'Common threats and how they reach you',
                    'slug' => 'common-threats',
                    'summary' => 'Malware, phishing, and password attacks in plain terms.',
                    'estimated_minutes' => 15,
                    'content' => <<<'TEXT'
Most people lose access to an account or a machine through three broad routes.

Malware is software written to cause damage or to give an attacker access. It
arrives most often disguised as something ordinary: an attachment that claims to
be an invoice, a download that is really an executable. Phishing is the attempt
to obtain credentials or payment by impersonating a trusted sender, usually by
email but increasingly by message or by a fake sign in page. Password attacks
include guessing a reused password from a list of known breaches, and guessing
variations of a password the attacker already knows.

None of these require the attacker to be clever. They require you to be briefly in
a hurry, which is why the countermeasures are mostly habits rather than
software. Slow down on an unexpected request for payment or credentials. Turn on
multi factor authentication where it is offered. Use a different password for each
important account, so one breach does not become several.

Urgency is the attacker's main tool. Recognising it is the main defence.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Three habits that prevent most incidents',
                            'content' => "1. Treat an unexpected request for money or credentials as suspect until verified.\n2. Turn on multi factor authentication for every account that offers it.\n3. Use a different password for each important account.\n\nEach one is free. Together they remove the routes used in most successful incidents.",
                        ],
                        [
                            'type' => 'external_link',
                            'title' => 'Further reading: recognising a phishing message',
                            'url' => 'https://www.cisa.gov/secure-our-world/recognize-and-report-phishing',
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: threats and defences',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'An email from a supplier asks you to pay an invoice urgently using a new bank account. What is the most likely explanation?',
                                    'explanation' => 'Urgency plus a change of payment details is the standard pattern of a business email compromise attempt.',
                                    'options' => [
                                        ['text' => 'An attempt to redirect a payment to an attacker, relying on urgency', 'correct' => true],
                                        ['text' => 'A routine reminder from the supplier', 'correct' => false],
                                        ['text' => 'A virus that has infected the supplier system', 'correct' => false],
                                        ['text' => 'An error by the supplier', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'Which of these reduces the effect of one breached website on your other accounts?',
                                    'explanation' => 'Unique passwords mean a breach of one account does not hand an attacker the key to the rest.',
                                    'options' => [
                                        ['text' => 'Using a different password for every important account', 'correct' => true],
                                        ['text' => 'Changing your password every day', 'correct' => false],
                                        ['text' => 'Using a longer username', 'correct' => false],
                                        ['text' => 'Disabling cookies', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'IT Careers and Professional Skills',
            'description' => 'Where the field goes and what working in it asks of you.',
            'lessons' => [
                [
                    'title' => 'Roles in information technology',
                    'slug' => 'roles-in-information-technology',
                    'summary' => 'The main branches of the field and what each one does day to day.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
The field divides into a few recognisable branches, and knowing roughly what each
one involves makes the later years of a computing degree easier to choose.

Network work covers keeping systems connected: configuring routers and switches,
tracing a fault, and keeping a site reachable. Systems administration covers the
servers, storage, and accounts that an organisation runs on. Technical support is
the front line, solving problems for people rather than components. Development
covers writing the software itself, and splits further into web, mobile, systems,
and data work. Quality assurance tests software by trying to break it. Security
specialists look for the weaknesses others missed.

Most people arrive in one branch and move towards another. A support role that
involves a lot of scripting is a reasonable path into systems work, and an
interest in networks often turns up inside a systems job. Treat the first degree
as a way to find the branch, not a commitment to it.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Branches at a glance',
                            'content' => "Network: connectivity, routing, and tracing faults.\nSystems administration: servers, storage, and accounts.\nTechnical support: solving problems for people.\nDevelopment: building software, from web to data.\nQuality assurance: testing software by trying to break it.\nSecurity: finding and closing weaknesses.",
                        ],
                    ],
                ],
                [
                    'title' => 'Professional skills that decide outcomes',
                    'slug' => 'professional-skills',
                    'summary' => 'Writing, documenting, and working in a team.',
                    'estimated_minutes' => 11,
                    'content' => <<<'TEXT'
Technical skill gets someone hired. Communication is what decides how far they go,
and it is the part that is hardest to practise on purpose.

Writing matters more than most students expect. A solution that only the author
understands has not really been solved. A short note explaining what you changed,
what you tried, and what is still broken saves the next person, including your
future self, an afternoon.

The other two habits worth naming are estimating and asking. Saying "this will
take a day" when you do not know is worse than saying "I do not know yet, I will
find out by three o'clock". And when you are stuck, the question worth asking is
specific: "here is what I expected, here is what happened, here is what I have
already tried".
TEXT,
                    'materials' => [],
                ],
            ],
        ],
    ],

];
