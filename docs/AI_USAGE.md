# AI Usage & Transparency Note

This document transparently outlines the usage of AI during the development of the Quantix Service Requests Take-Home Assignment.

---

## 1. Tool Used
- **Assistant**: Antigravity (Google DeepMind)
- **Role**: Pair programmer, code review assistant, and test-driven development collaborator.

## 2. Methodology & Boundaries
- **Strictly Grounded in Brief**: The AI was directed to adhere solely to the project specifications without inventing unrequested packages, features, or architectural complexity.
- **Incremental, Phased Implementation**: Work was organized into discrete steps (data modeling, workflow engine, auth/policies, endpoints, notifications, seeders, feature tests, quality audit, and documentation).
- **Test-Driven Verification**: Every phase was backed by automated PHPUnit tests executed against MySQL and verified by Larastan and Pint.
- **Domain Justification**: Design decisions (centralized state machine, thin controllers, backed enums, atomic DB transactions, and self-rendering exception handlers) were crafted so that every line of code can be explained and justified by the developer in a technical interview.

