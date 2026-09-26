<?php

/**
 * Introduction to Cybersecurity.
 *
 * The security course for a first year student. It is defensive throughout: the
 * aim is that a student recognises an attack, explains why a control works, and
 * can tell a real warning from a fake one.
 */

return [

    'title' => 'Introduction to Cybersecurity',
    'slug' => 'introduction-to-cybersecurity',
    'description' => 'A defensive introduction to security. Covers the CIA triad, the attacks that fill a news feed, how authentication actually works, network security basics, malware and phishing in detail, protecting personal data, and the habits that hold together.',
    'learning_objectives' => "Explain the CIA triad and give a real example of each.\nDescribe common attacks and how each one works.\nExplain what multi factor authentication does and why it helps.\nDescribe the basic controls on a network.\nDistinguish types of malware and recognise a phishing attempt.\nHandle personal data in line with the Data Privacy Act.\nApply a baseline of security habits to your own accounts and devices.",
    'category' => 'Cybersecurity',
    'level' => 'beginner',
    'course_type' => 'paid',
    'price_minor' => 150000,

    'modules' => [

        [
            'title' => 'Cybersecurity Fundamentals',
            'description' => 'The goals everything else serves.',
            'lessons' => [
                [
                    'title' => 'The CIA triad',
                    'slug' => 'the-cia-triad',
                    'summary' => 'Confidentiality, integrity, and availability, with real examples.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
Almost every security decision reduces to three goals.

Confidentiality means only the right people can read something. A student record
is confidential. Encryption, access control, and a strong password are all
confidentiality controls.

Integrity means the data is still correct. If an attendance record can be edited by
a student, the record is no longer trustworthy even if nobody can read it they
should not. Checksums, audit logs, and version history are integrity controls.

Availability means the system is there when it is needed. A perfectly secure
system that no one can log into has failed. Availability is why organisations plan
for backups and for what happens when a service goes down.

The three can conflict. Stronger protection can reduce availability, and faster
access can weaken confidentiality. Deciding which to favour is a judgement call
about what the system is for, which is why security is never a single setting.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'The triad with examples',
                            'content' => "Confidentiality: only the right people can read it.\n    Example: a grade is visible to the student who earned it and their instructor.\n\nIntegrity: the data is still correct.\n    Example: a submitted quiz answer cannot be changed after the deadline.\n\nAvailability: the system is there when it is needed.\n    Example: a student can reach their course at 9pm the night before a deadline.",
                        ],
                    ],
                ],
                [
                    'title' => 'Thinking like an attacker',
                    'slug' => 'thinking-like-an-attacker',
                    'summary' => 'The surface, the motivation, and why easy targets exist.',
                    'estimated_minutes' => 12,
                    'content' => <<<'TEXT'
An attacker looks for the cheapest way in, not the most impressive one. That is
the single most useful thing to understand about security, and it explains why
patching one old piece of software matters more than buying an expensive product.

Attackers are usually not trying to be subtle. They send the same message to
thousands of people because some fraction will reply. They try a handful of common
passwords because some accounts will use one. The maths of scale means a very
unsophisticated attack works well, which is why the average defence that stops it
is worth more than an exceptional defence against a sophisticated one.

Motivation varies. Some want money, some want recognition, some want to damage an
organisation they have a grievance with, and some are hired. Understanding the
likely motive tells you what to protect first.

Security is also a shared responsibility. A perfectly configured server is still
compromised if the account holder types their password into a convincing fake
page, which is why the human end of the problem keeps coming back.
TEXT,
                    'materials' => [],
                ],
            ],
        ],

        [
            'title' => 'Common Threats and Attacks',
            'description' => 'The attacks that actually happen, and how each one works.',
            'lessons' => [
                [
                    'title' => 'Social engineering and credential attacks',
                    'slug' => 'social-engineering',
                    'summary' => 'Manipulating a person rather than attacking a machine.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
Social engineering targets the person instead of the software. It is effective
because it works on a human being doing a reasonable job, usually under time
pressure.

The most common form is pretexting: inventing a plausible situation to get
someone to do something. A caller claiming to be from the registrar, asking for a
password "just to check the account". An email claiming to be a supplier, changing
bank details. Both are the same attack with a different costume.

Credential attacks use lists rather than cleverness. In a credential stuffing
attack, a list of known email and password pairs is tried against a different
site automatically. This works wherever people reuse passwords, which is most
places.

The defence is mostly behavioural. Verify identity through a channel you chose, not
one the message supplies. Turn on multi factor authentication. Use a different
password for anything important, so a breach of one account is not a breach of
all of them.
TEXT,
                    'materials' => [],
                    'quizzes' => [
                        [
                            'title' => 'Check: social engineering',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'Why does credential stuffing work?',
                                    'explanation' => 'It tries known password pairs against other sites, so it succeeds wherever passwords are reused.',
                                    'options' => [
                                        ['text' => 'People reuse passwords, so one breach reaches many accounts', 'correct' => true],
                                        ['text' => 'The passwords are stored weakly by most websites', 'correct' => false],
                                        ['text' => 'The attack breaks the encryption directly', 'correct' => false],
                                        ['text' => 'It only works on old websites', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'What is the safest way to verify a request that arrives by message?',
                                    'explanation' => 'Contact the person through a channel you chose yourself, using a number or address you looked up.',
                                    'options' => [
                                        ['text' => 'Contact them through a channel you looked up yourself', 'correct' => true],
                                        ['text' => 'Reply to the message and ask if it is genuine', 'correct' => false],
                                        ['text' => 'Send the information if the request looks official', 'correct' => false],
                                        ['text' => 'Wait for them to contact you instead', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Attacks on a network',
                    'slug' => 'network-attacks',
                    'summary' => 'Sniffing, spoofing, denial of service, and man in the middle.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
A network attack targets the traffic between machines rather than the machines
themselves.

Packet sniffing reads traffic as it passes. On a wired network that an attacker is
connected to, this can be close to effortless, which is why encryption in transit
is treated as essential rather than optional.

Spoofing means pretending to be someone else by using their address. It is at the
root of many other attacks, and it is the reason an address alone proves nothing
about who is sending something.

A denial of service attack aims to make a service unavailable by overwhelming it,
or by sending something it cannot answer cheaply enough to keep up with. The
second kind is the more interesting one to defend, because it exploits the work the
server does on request.

A man in the middle attack positions the attacker between two parties who believe
they are talking to each other. It is defeated by proper certificate validation,
which is exactly what encrypted connections do.
TEXT,
                    'materials' => [],
                ],
            ],
        ],

        [
            'title' => 'Passwords and Authentication',
            'description' => 'How proving who you are actually works.',
            'lessons' => [
                [
                    'title' => 'What a password really is',
                    'slug' => 'what-a-password-is',
                    'summary' => 'Storing one, and why length beats complexity.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
A password should never be stored as typed. A system that needs to check a password
stores a one-way result of it instead: a hash. Hashing runs the password through a
process that cannot be reversed, so the system checks whether the stored hash
matches the hash of what was typed. If the database is stolen, the attacker gets
hashes rather than passwords.

Hashes are deliberately slow. A slow algorithm is inconvenient for a user logging
in once and very expensive for an attacker trying billions of combinations, which
is the asymmetry the design relies on.

For choosing a password, length beats complexity. A long passphrase is easier to
remember and far harder to crack than a short string of symbols, and the arithmetic
is not close. Each extra character multiplies the number of guesses needed.

A salt is stored alongside the hash and is different for every user, so that two
people with the same password do not produce the same hash, and one precomputed
table cannot be applied to everyone at once.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Storing a password safely',
                            'content' => "1. Hash the password with a deliberately slow algorithm.\n2. Generate a random salt for each user and store it beside the hash.\n3. Never log the password, never return it in an API, never put it in a URL.\n4. Reset by issuing a new random value, not by asking the old password.\n\nLength beats complexity: each extra character multiplies the guesses needed.",
                        ],
                    ],
                ],
                [
                    'title' => 'Multi factor authentication',
                    'slug' => 'multi-factor-authentication',
                    'summary' => 'A second proof of identity, and why it works.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
Single factor authentication uses one thing: something you know, which is the
password. Multi factor authentication requires a second, independent proof from a
different category: something you have, such as a phone, or something you are, such
as a fingerprint.

The value is that a stolen password alone is no longer enough. An attacker who
obtains a password list still cannot log in, because they do not have the second
factor. This is the single change that removes the majority of successful account
takeovers, and it is worth doing before anything more sophisticated.

Not all second factors are equal. A code sent by message is much weaker than one
from an authenticator app, because an attacker who can intercept messages can also
intercept the code. A code generated on your own device is better, and better
still is a hardware key that only responds on the site it belongs to.

The remaining weakness worth knowing: a convincing phishing page can relay a code
in real time. Phishing resistant factors were built for exactly that.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Factors, strongest first',
                            'content' => "Strongest: a hardware security key, which only responds on the site it belongs to.\nStrong: a code from an authenticator app, generated on your device.\nWeaker: a code sent by message, which can be intercepted along with the message.\n\nAny second factor is a large improvement over a password alone. Not all are equal.",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: authentication',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'Why are password hashes made deliberately slow?',
                                    'explanation' => 'The slowness is negligible for a user logging in once and very expensive for an attacker trying billions of guesses.',
                                    'options' => [
                                        ['text' => 'The cost is negligible for a user but huge for someone trying billions of guesses', 'correct' => true],
                                        ['text' => 'It makes the hash harder to write down', 'correct' => false],
                                        ['text' => 'It makes passwords shorter', 'correct' => false],
                                        ['text' => 'It is required by the operating system', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'What does multi factor authentication protect against most directly?',
                                    'explanation' => 'A stolen password alone is no longer enough to log in.',
                                    'options' => [
                                        ['text' => 'An attacker who has a password but not the second factor', 'correct' => true],
                                        ['text' => 'A stolen laptop that is still switched on', 'correct' => false],
                                        ['text' => 'A website that collects too much information', 'correct' => false],
                                        ['text' => 'A slow internet connection', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Network Security Basics',
            'description' => 'The controls on a network and what each one is for.',
            'lessons' => [
                [
                    'title' => 'Firewalls, encryption, and least privilege',
                    'slug' => 'network-controls',
                    'summary' => 'Three controls that appear in almost every network.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
A firewall decides which traffic is allowed between networks. It compares each
connection against a set of rules, and everything that does not match is refused.
A packet filter looks only at the address and port. A stateful firewall also keeps
track of which connections it has already seen, which stops a forged return
address from being useful.

Encryption in transit protects data between two points. It does not hide which
sites you visited, so it is a privacy tool rather than an anonymity tool, and
conflating the two is a common mistake.

Least privilege means each account is given the minimum access needed to do its
job. It is dull and it prevents more damage than any other single control. An
account that only needs to read one table cannot delete it by accident, and a
compromised account has less to work with.

The habit that ties them together is default deny. Allow what is needed rather than
blocking what is known to be bad, because the list of bad things grows every day and
the list of things you actually need is short and stable.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Default deny',
                            'content' => "Weak: block a list of known bad ports and addresses. The list is always out of date.\n\nStronger: allow the handful of connections actually required, and refuse everything else.\n\nThe allow list is short, stable, and you can check it. That is why it is the better default.",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Malware and Phishing',
            'description' => 'The two most common infections, in enough detail to spot them.',
            'lessons' => [
                [
                    'title' => 'Types of malware',
                    'slug' => 'types-of-malware',
                    'summary' => 'Virus, worm, trojan, ransomware, and spyware.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
The names are worth learning because they describe different behaviour, and
different behaviour needs different responses.

A virus attaches to a file and spreads when the file is opened. It needs a carrier.

A worm spreads on its own across a network, using the network itself as the carrier.
Worms spread quickly for exactly that reason.

A trojan does not spread. It pretends to be something useful, and is installed
because the user chose to install it.

Ransomware encrypts files and then demands payment for the key. Modern variants
also steal data first and threaten to publish it, which is why paying is not
guaranteed to help and may fund the next one.

Spyware and keyloggers record what is typed and what is browsed, and send it
somewhere. They are usually installed without any visible symptom, which is what
makes them worth looking for rather than waiting for.

Every one of these is delivered by convincing a person. The software is the easy
part.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'The five at a glance',
                            'content' => "Virus: attaches to a file, spreads when opened.\nWorm: spreads on its own across a network.\nTrojan: does not spread, pretends to be useful.\nRansomware: encrypts files and demands payment, often after stealing them.\nSpyware: records activity quietly and sends it away.",
                        ],
                    ],
                ],
                [
                    'title' => 'Recognising a phishing attempt',
                    'slug' => 'recognising-phishing',
                    'summary' => 'The four things to check, in under a minute.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
Phishing has become harder to spot by eye because the writing is now good and the
logos are copied properly. The remaining tells are structural rather than
typographical.

Check the address, not the display name. The display name is chosen by the sender
and can be anything.

Check the real link before clicking. Hovering shows where it goes, and a familiar
name in the text can point somewhere else entirely.

Check whether the request makes sense. A password reset you did not ask for, an
invoice with no matching order, a message claiming your account will be closed
today: all of these are creating urgency on purpose.

Check whether the site asks for something it has no reason to need. No legitimate
service asks for your full card number, or for your password, by message.

And the one that stops everything: if it worries you, do not engage with it. Open
the site yourself and check. The cost of checking is a few seconds; the cost of
being wrong is an account.
TEXT,
                    'materials' => [
                        [
                            'type' => 'external_link',
                            'title' => 'Reference: recognising and reporting phishing',
                            'url' => 'https://www.cisa.gov/secure-our-world/recognize-and-report-phishing',
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: malware and phishing',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'What distinguishes a worm from a virus?',
                                    'explanation' => 'A worm spreads on its own across a network, while a virus needs a file to be opened.',
                                    'options' => [
                                        ['text' => 'A worm spreads by itself across a network, a virus needs a file to be opened', 'correct' => true],
                                        ['text' => 'A worm is larger than a virus', 'correct' => false],
                                        ['text' => 'A worm only affects Windows', 'correct' => false],
                                        ['text' => 'A virus always deletes files', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'A message says your account will close today unless you sign in. What is the right response?',
                                    'explanation' => 'Artificial urgency is the core technique. Open the site yourself rather than using the link.',
                                    'options' => [
                                        ['text' => 'Open the site yourself and check, rather than using the link in the message', 'correct' => true],
                                        ['text' => 'Sign in quickly so the account is not closed', 'correct' => false],
                                        ['text' => 'Reply asking who sent it', 'correct' => false],
                                        ['text' => 'Forward it to a colleague to see what they think', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Data Privacy and Protection',
            'description' => 'Personal data, and what the law requires of an organisation holding it.',
            'lessons' => [
                [
                    'title' => 'Personal data and the Data Privacy Act',
                    'slug' => 'personal-data-and-the-law',
                    'summary' => 'What counts as personal data, and the principles that apply.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
Personal data is any recorded information about an identifiable person. That
includes more than a name and an address. A student number, a class list with
grades, and a photograph in a group shot are all personal data in the Philippine
Data Privacy Act.

The Act's core principles are worth knowing because they apply wherever you handle
someone's information, including a student project holding class data. Personal
data must be processed lawfully and fairly. It must be collected for a stated,
legitimate purpose and not used for something incompatible with that purpose. It
must be accurate and kept up to date. It must not be retained longer than
necessary. It must be kept secure. And the person it describes has rights over it,
including the right to know what is held and to correct it.

Those rights include access, correction, and objection. A reasonable response to a
request for someone's data is to provide it, not to ask why.

The practical reason to care is that a student project that collects real class
data is handling real personal information, and "it is only a project" is not an
exemption.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'The principles, in short',
                            'content' => "Lawful and fair: collected and used honestly.\nLegitimate purpose: stated up front, and not stretched afterwards.\nAccurate: kept correct and current.\nNo longer than necessary: retention has to have an end.\nSecurity: protected against loss, damage, and unauthorised access.\nRights: the person can know what is held, correct it, and object.",
                        ],
                    ],
                ],
                [
                    'title' => 'Data minimisation',
                    'slug' => 'data-minimisation',
                    'summary' => 'The cheapest privacy control is not collecting it.',
                    'estimated_minutes' => 12,
                    'content' => <<<'TEXT'
The cheapest way to protect data is not to hold it. Every field a system does not
store cannot leak, cannot be subpoenaed, and never has to be deleted.

Before adding a field to a form, ask what breaks if it is not collected. A
birthdate is often added out of habit. A student number may already identify the
person uniquely, making the name optional for internal use. Collecting less is
usually simpler as well as safer.

The same applies to logs and analytics. Anything recorded for convenience becomes
data you are responsible for, and a log full of full request bodies is a liability
rather than an asset.

When a field is genuinely needed, record it once and reference it rather than
copying it around. Copies drift out of sync, and then the two disagree and you no
longer know which is correct.
TEXT,
                    'materials' => [],
                ],
            ],
        ],

        [
            'title' => 'Security Best Practices',
            'description' => 'The habits that hold all of it together.',
            'lessons' => [
                [
                    'title' => 'For your own accounts and devices',
                    'slug' => 'personal-security-habits',
                    'summary' => 'A short baseline that covers most incidents.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
A short list covers the large majority of real incidents. None of it is difficult.

Use a different password for each important account, stored in a password manager
rather than in your head or in a note. Turn on multi factor authentication
everywhere it is offered. Keep your devices updated, because most successful
exploits use a flaw that was already patched. Turn on automatic locking, so a
walked-away device is not an open one. Encrypt the drive on a laptop you travel
with. Back up important files, and test that a restore works.

The backup point is the one people skip. A backup that has never been restored is a
rumour, and ransomware will happily encrypt the backup if it is reachable from the
same machine.

None of this is exotic. That is the point: the most effective security measures
available to an individual are free, take an afternoon to set up, and are ignored
by most people who are not in this course.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'The baseline',
                            'content' => "1. A different password for each important account, kept in a password manager.\n2. Multi factor authentication on everything that offers it.\n3. Automatic updates on every device.\n4. Automatic screen lock.\n5. Disk encryption on a portable device.\n6. Backups, with a restore actually tested.\n\nFree, an afternoon to set, and skipped by most people not studying this.",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Final Security Assessment',
            'description' => 'Putting the course together on realistic scenarios.',
            'lessons' => [
                [
                    'title' => 'Assessing a real situation',
                    'slug' => 'security-assessment',
                    'summary' => 'Applying the course to scenarios that have no clean answer.',
                    'estimated_minutes' => 15,
                    'content' => <<<'TEXT'
The assessment is a set of scenarios rather than a set of definitions, because the
skill being tested is judgement rather than recall.

A student receives an email that appears to be from the university, asking them to
confirm their password before their account is deactivated. A group project needs
to collect names and numbers from classmates. An instructor shares a spreadsheet
containing every student's grades through a link that requires no sign in. A
computer in the laboratory is slow and a technician says it needs to be
reinstalled.

Each has a defensible answer and each has a trap. The email is phishing, and the
trap is replying to ask if it is real. The project needs a stated purpose and
consent. The spreadsheet is a confidentiality failure that has already happened, and
the trap is treating it as a future problem. The slow computer is a distraction
from the real question, which is why reimaging a working machine is a last resort
rather than a first one.

For each scenario, state the risk, name the control that addresses it, and say what
you would do first. Being able to say what you would do first is the whole skill.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'How to approach a scenario',
                            'content' => "1. What is the asset, and who is it supposed to be for?\n2. What is the specific threat, not the general category?\n3. Which control reduces it, and which only makes you feel better?\n4. What would you do first, today, before anything else is in place?\n5. What would tell you the control is not working?",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: final assessment',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'A spreadsheet of student grades is shared through a link that requires no sign in. What is the primary problem?',
                                    'explanation' => 'Anyone with the link can read the grades, which is a confidentiality failure that has already happened.',
                                    'options' => [
                                        ['text' => 'Anyone with the link can read it, so confidentiality has already failed', 'correct' => true],
                                        ['text' => 'The file is too large to email', 'correct' => false],
                                        ['text' => 'Grades should be stored on paper only', 'correct' => false],
                                        ['text' => 'The link will expire too soon', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'Which of these is the strongest reason to back up important files?',
                                    'explanation' => 'Ransomware can encrypt a backup that is reachable from the same machine, and a backup that has never been restored may not work at all.',
                                    'options' => [
                                        ['text' => 'Ransomware can reach a backup on the same machine, so backups need testing', 'correct' => true],
                                        ['text' => 'It makes the files open faster', 'correct' => false],
                                        ['text' => 'It is required by the Data Privacy Act', 'correct' => false],
                                        ['text' => 'It reduces the need for multi factor authentication', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'Why is a firewall configured as default deny stronger than one that blocks known bad addresses?',
                                    'explanation' => 'The list of bad addresses grows constantly, while the list of genuinely required connections is short and stable.',
                                    'options' => [
                                        ['text' => 'The list of required connections is short and stable, while the bad list always grows', 'correct' => true],
                                        ['text' => 'Default deny uses less memory', 'correct' => false],
                                        ['text' => 'It blocks attacks that default allow permits', 'correct' => false],
                                        ['text' => 'Default deny encrypts the traffic', 'correct' => false],
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
