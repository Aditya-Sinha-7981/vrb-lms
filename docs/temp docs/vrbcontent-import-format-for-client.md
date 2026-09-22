# VRB LMS content import — CSV format guide

Content is loaded via our custom "Import Content" tool
(Site administration → Reports → Import Content, `local_vrbcontent`
plugin). It takes plain CSV files. There are two separate formats — one
for course reading material (Book chapters), one for quiz questions.

## 1. Book content (`sample-vrbcontent-book-import.csv`)

Each row becomes one chapter. Column headers are **not fixed** — you can
use whatever column names make sense for your content (Product Name,
Ingredient, Step, etc.), because when uploading, we map each column to a
field and mark **one column as the chapter title**. Every other column
becomes a labeled section inside that chapter.

Rules:
- First row = column headers, exactly as they'll appear as labels in the
  chapter (e.g. "SKU" shows as "SKU:" above its value).
- One column must be designated the title — that value becomes the
  chapter heading. (In the sample, "Product Name".)
- A blank cell is allowed — it's just shown as "—" in the chapter, it
  won't block the import.
- If a value isn't finalized yet, it's fine to write something like
  "Confirm with pricing team" or "TBD" — the tool flags these as a
  warning during preview so we notice them, but doesn't block the
  import or change the text.
- Every row must have the same number of columns as the header row.

You can send us multiple CSVs like this — one per Book/module — using
whatever column set fits that content (it doesn't have to be 5 columns
like the sample; could be 2, could be 10).

## 2. Quiz questions (`sample-vrbcontent-quiz-import.csv`)

This format is **fixed** — these exact column headers, in this exact
order, one row = one multiple-choice question with a single correct
answer:

| Column | Required? | Notes |
|---|---|---|
| Q.No | Yes | Must be unique within the file |
| Topic | Recommended | Used for grouping/labeling only |
| Question | Yes | The question text |
| A / B / C / D | At least 2 of these 4 | Leave unused options blank (see row 3 and 5 in the sample, which only use 3 and 2 options) |
| Correct Answer | Yes | Must be a single letter (A/B/C/D) matching one of the filled-in options above |
| Explanation | Recommended | Shown to the learner as feedback after answering; left blank if not provided |

Things that will block an import (need fixing before we can load the
file): empty Q.No, duplicate Q.No, empty Question text, fewer than 2
options filled in, or a Correct Answer letter that doesn't match a
filled-in option.

Things that are allowed but flagged as a warning only: blank Topic,
blank Explanation.

## Files in this folder

- `sample-vrbcontent-book-import.csv` — 3 example rows for Book content
- `sample-vrbcontent-quiz-import.csv` — 5 example quiz questions
