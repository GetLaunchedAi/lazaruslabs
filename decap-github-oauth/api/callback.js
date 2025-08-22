import fetch from "node-fetch";

export default async function handler(req, res) {
  try {
    const { code } = req.query;
    const { GITHUB_CLIENT_ID, GITHUB_CLIENT_SECRET } = process.env;

    if (!code) return res.status(400).json({ error: "Missing code" });

    // Exchange code for access_token
    const tokenRes = await fetch("https://github.com/login/oauth/access_token", {
      method: "POST",
      headers: { "Accept": "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({
        client_id: GITHUB_CLIENT_ID,
        client_secret: GITHUB_CLIENT_SECRET,
        code
      })
    });
    const tokenJson = await tokenRes.json();
    if (!tokenRes.ok || !tokenJson.access_token) {
      return res.status(500).json({ error: tokenJson.error || "Token exchange failed", details: tokenJson });
    }

    // Decap expects JSON with a token
    res.status(200).json({ token: tokenJson.access_token });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
}
