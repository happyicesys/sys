# Style rules for the ConnectVend feature guide (for writers; not served)

Readers: operator admins and staff (often in Indonesia) asking Claude "how do I ... ?".
Claude reads these files and answers them, translating to their language when needed.

MUST
- Plain, short English. Second person ("Open ...", "Press ...").
- The system is called **ConnectVend**. Never write mark1, Laravel, Vue, Inertia, MQTT
  topics, server names, URLs, file paths, class/table/column names, or code of any kind.
- Menu paths exactly as on screen: **Menu ▸ Submenu**, e.g. **Daily Jobs ▸ Jobs**.
- Button, tab, column, filter and field names exactly as the screen labels them
  (check the label strings in the page; use the English label), in **bold**.
- For each screen: `## <Menu ▸ Submenu>` then
  - **What it is for** (1–3 sentences)
  - **What you see** (main columns / boxes / filters, one line each; only the useful ones)
  - **How to ...** sections for the 2–6 most common tasks, numbered steps
  - **Good to know** (rules that surprise people: what a save changes on the machine,
    what cannot be undone, when a button is greyed out or hidden, who can see it in
    plain words like "admins only" or "needs the Operations permission")
- A feature that exists only for some setups (smart freezer, smart chiller, Grab,
  card terminals, a country) says so in one line at the top of its section.
- Money: say "amount" in the operator's currency; never explain cents/storage.
- Do not invent. If unsure what a button does, say less, not more. Never describe a
  screen you did not open in the code.

MUST NOT
- Copy code, SQL, JSON, permission strings, environment names, or internal notes.
- Mention security internals, keys, tokens' format, payment-provider credentials.
