## Top-level purpose

**Answer, reliably and after the fact, three questions about a domain object: what changed, when did it change, and what did it look like before and after — without the application having to hand-write tracking code at every mutation site.**

Everything else is in service of that one sentence. Breaking it down:

## Core responsibilities

**1. Capture state.** Given a domain object at a point in time, produce a structured representation of the parts of it that matter for auditing — not necessarily everything the object contains, and not necessarily in a form that could rehydrate the object (that's serialization's job, not audit's). This representation needs to be comparable later, which means it needs to be stable and self-contained — it can't rely on live references to the object it came from.

**2. Compare two captured states.** Given "before" and "after," produce a structured description of the difference — not a boolean "did it change," but *what specifically* changed: which properties, which collection elements, added vs. removed vs. modified, at whatever depth the object graph actually has. The granularity of the diff should match the granularity that's actually useful to query later — coarse enough to be readable, fine enough that "what happened to this one nested thing" is answerable without re-deriving it from a blob.

**3. Correlate related changes into one logical event.** A single meaningful action ("user submitted this order") often produces many individual field/collection changes across one or more objects. The library needs a way to group those together under one identifier, so they can be reconstructed as "everything that happened as a result of this one thing" rather than a scatter of unrelated-looking rows.

**4. Leave room for who/why without owning it.** Every real audit trail eventually needs actor identity, request context, or business justification attached to a change. The library shouldn't invent its own notion of "user" or "reason" — that's the application's domain, and it varies per consumer. But it needs a clean, unopinionated slot for the application to attach that context, so it ends up on the record without the library having any opinion about what's in it.

**5. Produce a storage-agnostic output.** The end product of capture + diff + correlation should be a well-typed, serializable description of "this happened" — not a database row, not a log line, not tied to any particular persistence shape. Where and how it's stored (a table, a document store, an append-only file) is a downstream decision the library shouldn't make on the consumer's behalf.

**6. Be declarative to use.** The object owner should be able to say "this property is tracked" / "this collection is tracked, keyed by this identity" as a property of the class itself, and get capture/diff behavior for free — rather than writing procedural "record this field changed" code at every mutation call site. The tracking surface should live next to the data it describes.

**7. Fail loudly and specifically, never silently wrong.** If some value can't be meaningfully captured or diffed (something with no stable representation, something that would collapse into a different shape than intended), the library should refuse and say exactly why — never silently produce a technically-valid-looking record that's actually lossy, wrong, or misleading. An audit trail that's quietly incomplete is worse than one that's loudly broken, because nobody goes looking for the gap.

## Explicit non-goals

Just as important as what it does:

- **Not a persistence layer.** No schema, no table design, no query interface — that's for whatever consumes its output.
- **Not an identity/access system.** It doesn't authenticate, authorize, or track "who's logged in" — it only carries whatever identity the caller already has.
- **Not a general serialization/rehydration library.** Audit-relevant state and "everything needed to reconstruct the object" are different surfaces; conflating them either bloats every audit record or breaks object reconstruction.
- **Not a general-purpose deep-diff utility.** It's specifically for things with identity and a declared audit surface — not for comparing arbitrary trees, DTOs, or config objects with no notion of "collection membership" or "entity identity."
- **Not a messaging/event-bus system.** It produces records of what happened; dispatching, notifying, or reacting to those records is somebody else's concern.