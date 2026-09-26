<?php

/**
 * Programming Fundamentals with Python.
 *
 * The first paid course, and the first one where the student writes code. The
 * material is pitched so that every module ends with something that runs, because
 * a beginner loses motivation fastest when nothing works for three hours.
 */

return [

    'title' => 'Programming Fundamentals with Python',
    'slug' => 'programming-fundamentals-with-python',
    'description' => 'A practical first course in programming using Python. It covers the ideas every language shares, then applies them in Python: syntax, variables and types, decisions and repetition, functions, collections, file handling, and a small project that brings the pieces together.',
    'learning_objectives' => "Explain what a program is and how an interpreter executes it.\nStore and convert values using variables and Python's basic types.\nWrite decisions and loops that read clearly.\nBreak a problem into functions and reuse them.\nWork with lists, dictionaries, and sets.\nRead and write files, and handle errors without crashing.\nBuild a small program that does something genuinely useful.",
    'category' => 'Programming',
    'level' => 'beginner',
    'course_type' => 'paid',
    'price_minor' => 120000,

    'modules' => [

        [
            'title' => 'Programming Concepts',
            'description' => 'The ideas that apply to any language.',
            'lessons' => [
                [
                    'title' => 'What a program is',
                    'slug' => 'what-a-program-is',
                    'summary' => 'Instructions, the processor, and why a computer only does what it is told.',
                    'estimated_minutes' => 12,
                    'content' => <<<'TEXT'
A program is a sequence of instructions precise enough that a machine can carry
them out without guessing. That word precise is doing the work. A human can be
told "add the numbers" and decide for themselves what the numbers are. A computer
cannot: it needs to be told which numbers, in which order, and what to do with the
answer.

Because a computer follows instructions literally, most programming errors are not
clever problems. They are misunderstandings made visible: a name spelled two ways,
a step forgotten, an assumption that turned out to be wrong for one input out of a
thousand. Learning to program is largely learning to be precise.

Programs run in two different ways. A compiler translates the whole program into
another form before it runs. An interpreter reads and carries out one instruction at
a time. Python uses the second approach, which is why you can type a line and get
an answer immediately, and why a mistake often shows up as soon as that line runs
rather than at the end.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Compiler and interpreter',
                            'content' => "Compiler: translates the entire program first, then runs it. Errors are found before execution starts.\nInterpreter: reads and executes one instruction at a time. You get feedback immediately, and errors appear where they occur.",
                        ],
                    ],
                ],
                [
                    'title' => 'Algorithms and flow',
                    'slug' => 'algorithms-and-flow',
                    'summary' => 'The solution before the code.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
An algorithm is a finite sequence of steps that solves a class of problems. The
word finite is the important one. A step that can repeat forever is not an
algorithm, however sensible it looks.

Writing the algorithm before the code is the habit that separates people who
program from people who fiddle. The temptation is to start typing and discover
what the problem actually was halfway through. Two or three minutes of
instructions on paper prevents that.

Three constructs cover every algorithm you will write in your first year:
sequence, which is steps in order; selection, which is a decision; and iteration,
which is repetition. If a solution seems to need a fourth, it usually needs one
of these used more carefully.

Test the algorithm on paper with a small example before writing it in code. If it
does not work for three, you have found the problem while it is still cheap to
change.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'The three constructs',
                            'content' => "Sequence: do these steps in this order.\nSelection: if this condition holds, do this, otherwise do that.\nIteration: do this again while something remains.\n\nEvery algorithm is a combination of these three.",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: programs and algorithms',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'Why must an algorithm be finite?',
                                    'explanation' => 'A program that could run forever would never finish and never produce its result.',
                                    'options' => [
                                        ['text' => 'Because a program that runs forever never produces its result', 'correct' => true],
                                        ['text' => 'Because computers have no memory for long running tasks', 'correct' => false],
                                        ['text' => 'Because a compiler cannot translate an infinite sequence', 'correct' => false],
                                        ['text' => 'Because a user will always cancel it', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'Which three constructs cover every algorithm in a first programming course?',
                                    'explanation' => 'Sequence, selection, and iteration combine to make any solution.',
                                    'options' => [
                                        ['text' => 'Sequence, selection, and iteration', 'correct' => true],
                                        ['text' => 'Input, process, and output', 'correct' => false],
                                        ['text' => 'Variables, functions, and loops', 'correct' => false],
                                        ['text' => 'Memory, storage, and output', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Python Setup and Syntax',
            'description' => 'Getting Python running and writing valid statements.',
            'lessons' => [
                [
                    'title' => 'Installing and running Python',
                    'slug' => 'installing-and-running-python',
                    'summary' => 'Getting an interpreter and running your first line.',
                    'estimated_minutes' => 11,
                    'content' => <<<'TEXT'
Python is free and runs on Windows, macOS, and Linux. Download it from the
official site, install it, and you have both the interpreter and the development
environment.

You will meet two ways of using it. The interactive prompt, also called the repl,
reads one line at a time and shows the result immediately. It is the right place to
try one idea and see what happens. A script is a saved file of statements that runs
from beginning to end. That is what you use for anything longer than three lines.

The distinction matters more than it sounds. Most beginners try to build a whole
program in the interactive prompt, which makes correcting a mistake painful. Use
the prompt to answer a small question, then write the answer into a file.
TEXT,
                    'materials' => [
                        [
                            'type' => 'external_link',
                            'title' => 'Download Python',
                            'url' => 'https://www.python.org/downloads/',
                        ],
                    ],
                ],
                [
                    'title' => 'Indentation, statements, and comments',
                    'slug' => 'indentation-and-statements',
                    'summary' => 'Why Python uses indentation to mean something.',
                    'estimated_minutes' => 12,
                    'content' => <<<'TEXT'
In most languages, indentation is a matter of taste and braces mark the blocks of a
program. In Python, indentation is the syntax. A line indented further belongs to
the block above it, and a line at the wrong indentation is a syntax error rather
than a style complaint.

Be consistent within a file. Four spaces per level is the convention, and mixing
tabs with spaces causes errors that are hard to read. Most editors can be set to
insert spaces when you press the tab key, which removes the problem at the source.

A comment is a note for a reader. In Python it starts with a number sign and runs
to the end of the line. Comment the reasons, not the mechanics. A comment that says
"increment the counter" adds nothing, because the line already says that. A comment
that says "the enrolment record caps at thirty students, so this is checked rather
than trusted" is worth a great deal six months later.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'Indentation, right and wrong',
                            'content' => "# Correct: the line below is indented four spaces\nif score >= 80:\n    print('Pass')\n\n# Wrong: a tab here raises IndentationError\nif score >= 80:\n\tprint('Pass')",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Variables and Data Types',
            'description' => 'Naming, storing, and converting values.',
            'lessons' => [
                [
                    'title' => 'Variables and naming',
                    'slug' => 'variables-and-naming',
                    'summary' => 'Assignment, and the naming that makes code readable.',
                    'estimated_minutes' => 12,
                    'content' => <<<'TEXT'
A variable is a name that refers to a value. In Python you create one with an
equals sign, and you can change what it refers to at any time.

    total = 0
    total = total + 15

The second line does not change the number fifteen. It changes what the name total
points at, which is why a variable name should describe the meaning of the value
rather than its type. `total_score` is useful. `n` is not.

Python's naming convention is lower case with underscores between words, and it is
worth following even though the language allows other forms. A consistent
convention is one of the cheapest ways to make code readable to someone who has
never seen it, which at some point includes you.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'Assignment and renaming',
                            'content' => "# A name refers to a value and can be pointed somewhere else\ntotal = 0\ntotal = total + 15\n\n# The name should describe the meaning, not the type\nstudent_count = 42      # good\nn = 42                  # says nothing",
                        ],
                    ],
                ],
                [
                    'title' => 'The basic types and converting between them',
                    'slug' => 'types-and-conversion',
                    'summary' => 'Integers, floats, strings, and booleans.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
Python's core types are worth learning one at a time. An int is a whole number. A
float is a number with a decimal point. A str is text, written in quotes. A bool is
true or false.

    age = 19            # int
    price = 249.50      # float
    name = 'Shan'       # str
    is_enrolled = True  # bool

Two things surprise people. First, quotes matter: 19 is a number and '19' is two
characters that look like a number. They behave completely differently in
arithmetic.

Second, and more often, you will be given text where a number is needed. That
happens whenever a value comes from a keyboard, a file, or a web form, because all
of them deliver text. Converting is explicit:

    age = int('19')
    price = float('249.50')

If the text is not a valid number the conversion raises an error, which is covered
later in this course. The rule to remember: convert deliberately at the boundary
where the value enters your program, and keep it converted everywhere after that.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'Type conversion',
                            'content' => "# Text that looks like a number is still text\nvalue = '19'\nprint(value + 1)   # TypeError\n\n# Convert once, where the value enters the program\nvalue = int(value)\nprint(value + 1)   # 20",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: types and conversion',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'What is the type of the value "42"?',
                                    'explanation' => 'Quoted text is a string, not a number, however much it resembles one.',
                                    'options' => [
                                        ['text' => 'A string, because it is in quotes', 'correct' => true],
                                        ['text' => 'An integer, because it is a whole number', 'correct' => false],
                                        ['text' => 'A float, because it has a decimal point', 'correct' => false],
                                        ['text' => 'A boolean, because it is true or false', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'A value arrives from an input box as the text "20". What must happen before arithmetic on it?',
                                    'explanation' => 'Everything typed by a person arrives as text and has to be converted explicitly.',
                                    'options' => [
                                        ['text' => 'It must be converted to a number with int() or float()', 'correct' => true],
                                        ['text' => 'Nothing, because Python converts text automatically', 'correct' => false],
                                        ['text' => 'It must be saved to a file first', 'correct' => false],
                                        ['text' => 'It must be placed in a list', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Conditions and Loops',
            'description' => 'Making decisions and repeating work.',
            'lessons' => [
                [
                    'title' => 'Decisions with if, elif, and else',
                    'slug' => 'decisions',
                    'summary' => 'Comparisons and branching, including the mistake everybody makes once.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
A conditional runs a block only when its condition holds.

    if score >= 90:
        grade = 'A'
    elif score >= 80:
        grade = 'B'
    else:
        grade = 'C'

The order matters. Python checks each condition in turn and runs the first that
holds, so once a score of 95 matches the first condition it never reaches the
second. Reversing the branches turns every A into a B, and the code still runs
without complaint.

That is the trap in conditionals: a wrong ordering produces plausible wrong
answers rather than an error. The habit that prevents it is to order conditions
from most specific to least, and to test the boundaries. Check what happens at
exactly 80, and at exactly 79.

Use `==` to compare and `=` to assign. Mixing them up is the most common syntax
error in a first course, and Python's error message is clearer than you would hope.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'Ordering matters',
                            'content' => "# Correct: most specific first\nif score >= 90:\n    grade = 'A'\nelif score >= 80:\n    grade = 'B'\n\n# Wrong: 95 matches the second condition, so an A becomes a B\nif score >= 80:\n    grade = 'B'\nelif score >= 90:\n    grade = 'A'",
                        ],
                    ],
                ],
                [
                    'title' => 'Loops: for and while',
                    'slug' => 'loops',
                    'summary' => 'Repeating a fixed number of times, and repeating until a condition ends.',
                    'estimated_minutes' => 15,
                    'content' => <<<'TEXT'
A `for` loop repeats once for each item in a sequence. Use it whenever you know
what you are going over.

    for student in class_list:
        print(student)

A `while` loop repeats until its condition becomes false. Use it only when the
number of repetitions genuinely is not known in advance, because a condition that
never becomes false produces a loop that never ends.

    attempts = 0
    while attempts < 3:
        attempts = attempts + 1

Before writing a `while` loop, check what makes the condition false. If you cannot
say which line changes it, the loop will not terminate.

The other loop worth knowing is `for` over a range, which counts:

    for attempt in range(1, 4):   # 1, 2, 3
        print(attempt)

Note that the end value is excluded. `range(1, 4)` gives three values, not four.
This off by one catches everybody at least once, so it is worth testing with a
single iteration before trusting a loop that matters.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'The off by one',
                            'content' => "for attempt in range(1, 4):\n    print(attempt)\n\n# prints 1, 2, 3\n# the end value is excluded, so this is three values, not four",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Functions',
            'description' => 'Breaking a problem into named, reusable parts.',
            'lessons' => [
                [
                    'title' => 'Defining and calling functions',
                    'slug' => 'defining-functions',
                    'summary' => 'Parameters, return values, and why a function should do one thing.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
A function is a named block of code that takes inputs and gives back a result.

    def average(scores):
        return sum(scores) / len(scores)

The names inside the parentheses are parameters: names that exist only while the
function runs. The `return` statement sends a value back to whoever called it. A
function with no `return` gives back nothing, which is the usual explanation for a
result that prints as `None`.

A function should do one thing well. The test is whether you can describe it in a
single sentence without using the word "and". A function called
`calculate_average_and_print_report` is two functions that have not been split yet.

Return values rather than printing from inside a function. Printing ties the
function to the screen and makes it impossible to reuse in a report, a test, or
another program. Let the caller decide what to do with the result.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'A function that returns',
                            'content' => "def average(scores):\n    if not scores:\n        return 0\n    return sum(scores) / len(scores)\n\n# The caller decides what to do with the result\nprint(f'Average: {average([88, 92, 79]):.1f}')",
                        ],
                    ],
                ],
                [
                    'title' => 'Scope and default values',
                    'slug' => 'scope-and-defaults',
                    'summary' => 'Where a name is visible, and making a parameter optional.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
A variable created inside a function exists only inside it. That is a feature: it
means a helper can use a name like `total` without colliding with anything outside,
and you can change it without worrying about the rest of the program.

    def summarise(scores):
        total = sum(scores)   # local to this function
        return total

`total` cannot be used after the function returns. To bring a value out, return
it. To bring a value in, pass it as a parameter. Reading and writing globals from
inside functions is the usual cause of a bug that appears in one place and
mysteriously affects another.

A parameter can have a default, which makes it optional:

    def greet(name, greeting='Good morning'):
        return f'{greeting}, {name}'

Defaults must come after the required parameters, and a mutable default such as a
list is a well known trap, because it is created once and shared between calls. If
you need a list, create it inside the function instead.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'The mutable default trap',
                            'content' => "# Wrong: the list is created once and shared by every call\ndef add_item(item, bucket=[]):\n    bucket.append(item)\n    return bucket\n\n# Right: created fresh each time\ndef add_item(item, bucket=None):\n    if bucket is None:\n        bucket = []\n    bucket.append(item)\n    return bucket",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Lists, Dictionaries and Sets',
            'description' => "Python's three collection types and when each one fits.",
            'lessons' => [
                [
                    'title' => 'Lists',
                    'slug' => 'lists',
                    'summary' => 'Ordered, changeable, and indexable.',
                    'estimated_minutes' => 13,
                    'content' => <<<'TEXT'
A list holds an ordered sequence that can be changed. Positions are counted from
zero, so the first element is at index zero.

    scores = [88, 92, 79]
    scores.append(85)      # add to the end
    scores[0] = 90         # change the first
    len(scores)            # how many

Common operations worth knowing by heart are `append` to add, `remove` or `pop` to
take out, and `in` to test for membership. `scores.sort()` sorts in place and
returns nothing, which surprises people who write
`sorted_scores = scores.sort()` and find sorted_scores empty.

Iterating with `for` over a list is the most common loop in Python. When you need
the position as well, `enumerate` gives you both:

    for position, score in enumerate(scores, start=1):
        print(position, score)
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'List operations',
                            'content' => "scores = [88, 92, 79]\n\nscores.append(85)      # add to the end\nscores.insert(0, 95)  # add at the front\nlen(scores)            # 5\nscores[1]              # 88, because counting starts at zero\nscores.remove(92)      # remove the value 92\nlast = scores.pop()     # remove and return the last value",
                        ],
                    ],
                ],
                [
                    'title' => 'Dictionaries and sets',
                    'slug' => 'dictionaries-and-sets',
                    'summary' => 'Key and value pairs, and collections with no duplicates.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
A dictionary stores key and value pairs. You look things up by key rather than by
position, which is what you want when each item has a name.

    student = {'name': 'Shan', 'year': 1, 'enrolled': True}
    student['name']          # 'Shan'
    student.get('grade', 0)  # 0 if absent, rather than an error

The `get` method with a default is worth preferring over square brackets, because a
missing key raises an error and a missing value usually does not.

A set holds unique values with no order and no duplicates. It answers membership
questions quickly, which makes it the right tool for removing duplicates and for
finding what two lists have in common.

    submitted = {'Shan', 'Ana', 'Shan'}   # one value per person
    passed = {'Shan', 'Ana', 'Carlo'}
    submitted & passed                    # {'Shan', 'Ana'}
    submitted - passed                    # {'Carlo'}

Choosing between them is mostly a question of what identifies an item: a position
means a list, a name means a dictionary, and only membership mattering means a set.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'Dictionaries and sets',
                            'content' => "student = {'name': 'Shan', 'year': 1}\n\nfor key, value in student.items():\n    print(key, value)\n\n# A set removes duplicates automatically\nsubmitted = {'Shan', 'Ana', 'Shan'}\nprint(len(submitted))   # 2",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: collections',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'Which collection fits a record identified by a student id rather than a position?',
                                    'explanation' => 'A dictionary looks values up by key, which is what a named identifier needs.',
                                    'options' => [
                                        ['text' => 'A dictionary', 'correct' => true],
                                        ['text' => 'A list', 'correct' => false],
                                        ['text' => 'A set', 'correct' => false],
                                        ['text' => 'A tuple, because it is ordered', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'What does scores.sort() return?',
                                    'explanation' => 'sort() rearranges the list in place and returns nothing. Use sorted() for a new list.',
                                    'options' => [
                                        ['text' => 'Nothing; it rearranges the list in place', 'correct' => true],
                                        ['text' => 'The sorted list', 'correct' => false],
                                        ['text' => 'The number of items sorted', 'correct' => false],
                                        ['text' => 'The first and last values', 'correct' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'File Handling',
            'description' => 'Reading and writing, and not crashing on bad input.',
            'lessons' => [
                [
                    'title' => 'Reading and writing files',
                    'slug' => 'reading-and-writing-files',
                    'summary' => 'The with statement and the three modes you need.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
Files are opened, used, and closed. Use the `with` statement, which closes the file
for you even if something inside goes wrong.

    with open('results.txt', 'r') as handle:
        for line in handle:
            print(line.strip())

Three modes cover almost everything: `r` to read, `w` to write, which creates the
file and erases anything already in it, and `a` to append, which adds to the end
and leaves what is there.

The `w` mode is the one that causes lost work. Opening a file in write mode and
then failing partway through leaves a half written file and no original. When in
doubt, append.

Reading line by line is the pattern to prefer for anything large, because it does
not require the whole file to fit in memory at once.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'File modes',
                            'content' => "with open('notes.txt', 'r') as handle:   # read\n    text = handle.read()\n\nwith open('notes.txt', 'w') as handle:   # write, erases existing\n    handle.write('first line\\n')\n\nwith open('notes.txt', 'a') as handle:   # append, keeps existing\n    handle.write('second line\\n')",
                        ],
                    ],
                ],
                [
                    'title' => 'Handling errors without crashing',
                    'slug' => 'handling-errors',
                    'summary' => 'try, except, and the difference between handling and hiding.',
                    'estimated_minutes' => 15,
                    'content' => <<<'TEXT'
Some operations can fail in ways you can anticipate: a file that is not there, a
number that is not a number, a network that is down. A program that does not
handle these stops. One that does keeps going and can explain what happened.

    try:
        score = int(text)
    except ValueError:
        print('That was not a number.')

Catch the specific exception you expect. `except:` on its own hides every error
including your own typos, which makes a program that fails silently and cannot be
debugged.

`try` and `except` is not the same as ignoring a problem. A block that catches an
error and does nothing has turned a visible failure into an invisible one. If you
catch it, either do something useful about it or say clearly that you are skipping
it and why.

`finally` runs whether or not there was an error, which is the right place to close
something or write a summary.
TEXT,
                    'materials' => [
                        [
                            'type' => 'code',
                            'title' => 'Catching precisely',
                            'content' => "try:\n    score = int(text)\nexcept ValueError:\n    print('Not a number, skipping')\n\n# Too broad: hides your own mistakes too\ntry:\n    risky()\nexcept:\n    pass",
                        ],
                    ],
                ],
            ],
        ],

        [
            'title' => 'Mini Project',
            'description' => 'A small program that does something genuinely useful.',
            'lessons' => [
                [
                    'title' => 'Planning a small program',
                    'slug' => 'planning-a-project',
                    'summary' => 'Turning a vague requirement into steps and functions.',
                    'estimated_minutes' => 14,
                    'content' => <<<'TEXT'
A good first project is one that is small enough to finish and real enough to be
useful. A class marks calculator fits well. It has input, computation, a data
structure, a decision, and output, and it can be finished in an afternoon.

Break it into functions before writing any of them:

    def read_scores() -> list
    def average(scores: list) -> float
    def classify(score: float) -> str
    def report(scores: list) -> None

Each does one thing and can be tested on its own. `average([80, 90])` is a test you
can run without the rest of the program existing, which is the practical benefit of
splitting first.

Write the simplest version that runs, then improve it. Getting a working version on
screen first gives you something to test against, and the improvements become small
safe steps rather than one large rewrite.
TEXT,
                    'materials' => [
                        [
                            'type' => 'text',
                            'title' => 'Suggested project structure',
                            'content' => "1. read_scores: collect numbers from the user, handling bad input.\n2. average: return the mean, returning 0 for an empty list.\n3. classify: return a grade for a score, ordering conditions carefully.\n4. highest: return the best score, or None for an empty list.\n5. report: print a summary using the four above.\n\nAdd error handling and file output only after the core works.",
                        ],
                    ],
                    'quizzes' => [
                        [
                            'title' => 'Check: course review',
                            'is_required' => true,
                            'passing_score_percent' => 80.00,
                            'questions' => [
                                [
                                    'prompt' => 'Why split a program into functions before writing them?',
                                    'explanation' => 'Small functions can be tested on their own, which makes finding the fault much easier.',
                                    'options' => [
                                        ['text' => 'Each part can be tested on its own, so faults are easier to find', 'correct' => true],
                                        ['text' => 'Python does not allow long programs', 'correct' => false],
                                        ['text' => 'Functions run faster than inline code', 'correct' => false],
                                        ['text' => 'It removes the need for error handling', 'correct' => false],
                                    ],
                                ],
                                [
                                    'prompt' => 'What does a function return if it has no return statement?',
                                    'explanation' => 'It gives back None, which is the usual reason a result prints as None.',
                                    'options' => [
                                        ['text' => 'None', 'correct' => true],
                                        ['text' => 'Zero', 'correct' => false],
                                        ['text' => 'An empty string', 'correct' => false],
                                        ['text' => 'It raises an error', 'correct' => false],
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
