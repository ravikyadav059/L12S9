# Redis, Memurai & Redis Insight Guide

## 1. What is Redis?
**Redis** (Remote Dictionary Server) is an open-source, ultra-fast, in-memory key-value data structure store. Unlike traditional databases (like MySQL or MongoDB) that write primarily to hard disks/SSDs, Redis keeps all active data directly in **RAM (Memory)**.

- **Speed:** Sub-millisecond read & write operations (< 0.1ms).
- **Default Port:** `6379`.
- **Common Use Cases:** Fast caching, user sessions, real-time counters, pub/sub messaging, queues, leaderboards, and rate-limiting.

---

## 2. What is Memurai? (Redis on Windows)
Redis was originally built for Linux. On Windows systems, **Memurai** is the official, enterprise-grade Redis-compatible server for Windows.

- It runs natively as a Windows Service (`memurai.exe`).
- It is **100% wire-compatible** with Redis (version 7.x).
- Laravel communicates with Memurai on `127.0.0.1:6379` using standard Redis drivers (`phpredis` or `predis`), exactly as if it were running on a Linux production server.

### Checking Memurai Service on Windows:
In PowerShell / Command Prompt:
```powershell
# Check if Memurai is running
Get-Service memurai

# Start Memurai service if stopped
net start memurai

# Stop Memurai service
net stop memurai
```

---

## 3. What is Redis Insight?
**Redis Insight** is the official graphical user interface (GUI) desktop application for Redis and Memurai.

- **Download:** Official Redis desktop tool.
- **Connection:** Connects to `127.0.0.1:6379`.
- **Features:**
  1. **Key Browser:** Visual tree of all keys, cache entries, and data structures stored in memory.
  2. **Live Data Inspection:** View strings, sets, hashes, JSON documents, and expiration TTLs (time-to-live).
  3. **CLI Terminal:** Run raw Redis commands directly from the GUI (e.g. `KEYS *`, `PING`, `FLUSHDB`).
  4. **Memory & Performance Profiler:** Real-time RAM consumption charts and ops/second tracking.

---

## 4. Key Redis Data Structures Explained

| Data Structure | Description | Laravel / Scholar9 Example |
| :--- | :--- | :--- |
| **String** | Simple key-value text/JSON with optional expiration (TTL). | Cached HTML, question details, settings (`Cache::put('popular_tags', $tags, 3600)`). |
| **Set** | Unordered collection of unique strings (no duplicates allowed). | Fast user followers, bookmarks, saved question IDs (`Redis::sadd('user:123:saved', 'q456')`). |
| **Hash** | Mini dictionary / object with field-value pairs. | Storing a user session or question metadata without JSON encoding. |
| **List** | Ordered list of strings (FIFO/LIFO queue). | Activity log feeds, recent user views history (`LPUSH`, `LRANGE`). |
| **Sorted Set** | Sets ordered by a numeric score. | Trending questions leaderboard, top scholars score ranking. |
