export default function handler(req, res) {
  const { GITHUB_CLIENT_ID } = process.env;
  const scopes = ["repo", "public_repo"].join(" ");
  const redirect = `https://github.com/login/oauth/authorize?client_id=${encodeURIComponent(GITHUB_CLIENT_ID)}&scope=${encodeURIComponent(scopes)}`;
  res.status(302).setHeader("Location", redirect);
  res.end();
}
