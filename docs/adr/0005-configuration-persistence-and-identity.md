# Configuration persistence and identity: write-through, session-scoped

Two related decisions about how a Configuration comes into existence and stays saved:

**Every Answer persists immediately**, as it's given, rather than batching all Answers into one write when the Configurator is submitted. This was chosen over batching because a persistence path was needed regardless (to prove Answers actually reach the database), and write-through gives incidental durability — a crash or refresh mid-Configuration loses nothing — at no extra engineering cost over batching.

**An in-progress Configuration is identified by a value stored in the PHP session**, not a URL parameter and not authentication. A Livewire component loads the existing Configuration from session on mount, creating a new one if absent. This is deliberately *not* a "resume via shareable link" feature — a Respondent can't hand someone else a URL to continue their Configuration, and losing the session (e.g. clearing cookies) loses the Configuration. It exists only because write-through persistence needed some identity to write against, and session-scoping was the cheapest option consistent with v1 having no auth at all.
