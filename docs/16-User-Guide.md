# 16 · User Guide

**Status:** 🟡 In review · **Owner:** Product + Support · **Audience:** end users (donors, hospitals, admins) and operators.

LifeLine connects blood **donors** with **hospitals** and people in emergencies. This guide explains
how to use it by role. It describes the system as built today; features still in progress are marked
*(coming soon)* and tracked in Doc 15.

---

## 1. Getting started (everyone)

### Open the app
- Local/dev: `http://localhost:8000` (see the project README for setup).
- The top navigation gives you: **Home · Find Donors · Blood Banks · Eligibility · Emergency SOS ·
  Leaderboard · Stories**, plus **Login / Register** (or your dashboard once signed in).

### Create an account
1. Click **Register**.
2. Choose **Donor** or **Hospital**.
3. Fill the form. Passwords must be **8+ characters with an uppercase, a lowercase, a number, and a
   special character**.
4. Submit — you're logged in automatically and receive a welcome email.

### Sign in / out
- **Login** with your email + password. After 5 failed attempts your account is temporarily locked
  (~15 minutes) for security.
- **Logout** from the nav at any time.

### Forgot your password
1. **Login → Forgot password**, enter your email.
2. You'll get a reset link (valid **24 hours**, single use). For privacy, the site always says
   "if that email exists, we sent a link" — it won't confirm whether an address is registered.
3. Open the link, set a new password.

---

## 2. For Donors

### Your dashboard
After login, **Dashboard** shows:
- Your **status** — *Available*, *Busy* (engaged with a request), or *In cool-off* (with the date you
  become eligible again).
- **Active engagements** — requests you've confirmed for.
- **Matching open requests** — open requests compatible with your blood type.
- **Notifications** and your **tier & points**.

### Keep your profile current (this is what makes you reachable)
**Dashboard → Edit Profile:**
- **Blood type, city/state** — used to match you to nearby needs.
- **Availability** — toggle off when you can't donate; you won't be contacted.
- **Last donation date** — sets your **cool-off** so you're only shown when eligible.
- **Photo** *(optional)* — JPG/PNG/WebP, ≤2 MB; otherwise a clean initials avatar is shown.
- You can also change your email and password here (current password required to change it).

### Check your eligibility
**Eligibility** in the nav is a quick, private self-check (age, weight, recent donation, health
questions). It's guidance only — final clearance is always done by medical staff at donation time.

### Respond to a need
- From your dashboard or **Find Donors / a request page**, open a request and **Express Interest**
  when you're available. The hospital is notified and can contact you.
- You'll be reachable by hospitals only while you're marked **Available**.

### Earn recognition
Every recorded donation adds **points (+100)** and advances your **tier**: Bronze → Silver (5) →
Gold (10) → Platinum (20). See where you rank on the **Leaderboard** (all-time / this year / this month).

### Privacy
Your phone and email are shown to hospitals/other users **only when you're available and the viewer
is signed in**. You control reachability by toggling availability.

---

## 3. For Hospitals

### Your dashboard
**Dashboard** lists your blood requests (open and critical first) and recent notifications.

### Set up your profile
**Edit Profile:** hospital name, phone, address, city/state, **license number**. Verified hospitals
*(coming soon: explicit verification workflow)* gain a trust badge.

### Create a blood request
**Create Request:**
1. Choose **patient blood type** and **units needed**.
2. Set **urgency** — *Normal*, *Urgent*, or *Critical*.
3. Optionally set a **required-by date** and add **notes**. City/state prefill from your profile.
4. Submit — the request goes live as **Open**.

### Find & manage donors
Open a request → **Matches** to see **compatible, available donors** near you with their current
status. From here you:
- Move a donor through **Pending → Contacted → Confirmed → Donated** (or **Declined**).
- When you mark **Donated**, the system records it: the donor's history, donation count, points, tier,
  and cool-off all update automatically, and the donor is notified.
- Update the **request status** to **Fulfilled** or **Cancelled** when done.

> You can only see and manage **your own** requests.

---

## 4. Emergency SOS (anyone)

For an urgent need outside the hospital flow:
1. Open **Emergency SOS**.
2. Enter the **patient blood type**, **location**, **contact details**, and any notes.
3. Submit — a **Critical** request is created and **compatible, available donors in the area are
   notified by email** so they can respond fast.

*(Coming soon: a quick verification step and limits to keep SOS trustworthy and spam-free — Doc 15 P0.)*

---

## 5. Messaging & notifications (signed-in users)

- **Messages** in the nav opens your conversations; the badge shows unread count.
- Open a conversation to chat. Messages update **live** (no refresh needed). You can **copy, edit, or
  delete your own** messages.
- **Notifications** alert you to new messages, matches, and recorded donations, each linking to the
  relevant page.

---

## 6. Browsing without an account

- **Find Donors** — search by blood type and location (contact details require sign-in).
- **Blood Banks** — directory of blood banks by city/state with hours and contact.
- **Leaderboard** — top donors and tiers.
- **Stories** — approved testimonials from donors and recipients.
- **Eligibility** — the self-check questionnaire.

---

## 7. For Admins

Sign in with an admin account to reach **Admin → Dashboard**:
- **Overview** — counts of donors, hospitals, and open/critical requests.
- **Manage Donors / Hospitals / Requests** — paginated lists; **edit** or **delete** records.
- **Activity** — the audit log (filter by action/date) and **CSV export** of donors, hospitals, or
  requests for reporting.

> Admin actions are recorded in the audit trail. Deletions are permanent today *(soft-delete with
> retention is on the roadmap — Doc 15)*.

---

## 8. Tips & troubleshooting

| Situation | What to do |
|---|---|
| "I'm not getting requests" | Make sure **Availability** is on and your **blood type** and **city** are set. |
| "It says I'm in cool-off" | You donated recently; the date you become eligible again is shown on your dashboard. |
| Locked out after bad passwords | Wait ~15 minutes, then try again or use **Forgot password**. |
| Reset link doesn't work | Links expire after 24 hours and work once — request a new one. |
| No emails arriving | In dev, email may be logged rather than sent; check with your operator. |
| Contact details hidden | Sign in, and note a donor's details show only while they're **Available**. |

---

## 9. Safety & privacy promise

- Emergency **matching is free for patients — always**.
- Your contact details are shared on a **need-to-know** basis and only while you choose to be available.
- The app helps **find** donors; it never certifies medical fitness — that's done by clinical staff
  at the point of donation.

*Back to the [Documentation Index](00-Documentation-Index.md).*
