# MiniTube - A YouTube Clone

[span_0](start_span)[span_1](start_span)A simplified video-sharing web application built using **PHP**, **MySQL**, and **HTML5/CSS3**[span_0](end_span)[span_1](end_span). [span_2](start_span)[span_3](start_span)[span_4](start_span)It handles user authentication, video streams, channel subscriptions, and a nested comment tree[span_2](end_span)[span_3](end_span)[span_4](end_span).

[span_5](start_span)Developed as a Term Project for **CSE348: Database Management Systems (Spring 2026)**[span_5](end_span).

---

## 📂 Project Structure

```text
📂 cse348_project_copy/
 [span_6](start_span)├── 📂 data/                    # Text seeds and database files[span_6](end_span)
 [span_7](start_span)│    ├── first_names.txt[span_7](end_span)
 │    ├── last_names.txt
 │    ├── countries.txt
 │    ├── video_titles.txt
 │    ├── video_description.txt
 │    ├── comments.txt
 [span_8](start_span)│    └── seed.sql               # Generated INSERT statements[span_8](end_span)
 [span_9](start_span)├── 📂 html/                    # Frontend presentation templates[span_9](end_span)
 [span_10](start_span)│    ├── index.html             # Initialization page[span_10](end_span)
 [span_11](start_span)│    ├── login.html             # Login form[span_11](end_span)
 [span_12](start_span)│    ├── feed.html              # Homepage layout[span_12](end_span)
 [span_13](start_span)│    ├── channel.html           # Channel layout[span_13](end_span)
 [span_14](start_span)│    ├── watch.html             # Video player & comment layout[span_14](end_span)
 [span_15](start_span)│    └── sql.html               # Custom SQL terminal view[span_15](end_span)
 [span_16](start_span)└── 📂 php/                     # Backend controllers and logic[span_16](end_span)
      [span_17](start_span)├── install.php            # Creates database and tables[span_17](end_span)
      [span_18](start_span)├── generate_data.php      # Compiles mock datasets[span_18](end_span)
      [span_19](start_span)├── login.php              # Handles authentication[span_19](end_span)
      [span_20](start_span)├── feed.php               # Processes subscribed feeds[span_20](end_span)
      [span_21](start_span)├── channel.php            # Handles subscription actions[span_21](end_span)
      [span_22](start_span)├── watch.php              # View counter & comment handler[span_22](end_span)
      [span_23](start_span)└── sql.php                # Runs arbitrary user queries[span_23](end_span)
