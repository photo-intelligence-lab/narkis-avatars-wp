# Narkis Avatars for WordPress

Give commenters without a Gravatar a realistic AI-generated face instead of the grey Mystery Person. Free, no API key, no tracking.

![faces](https://narkis.ai/api/avatar/64/a.jpg) ![faces](https://narkis.ai/api/avatar/64/b.jpg) ![faces](https://narkis.ai/api/avatar/64/c.jpg) ![faces](https://narkis.ai/api/avatar/64/d.jpg) ![faces](https://narkis.ai/api/avatar/64/e.jpg) ![faces](https://narkis.ai/api/avatar/64/f.jpg)

- Adds **"Narkis AI face (generated)"** under Settings > Discussion > Default Avatar.
- Gravatar still wins: people who have one keep it. Optional switch to skip Gravatar entirely.
- Same commenter, same face (seeded by an md5 hash; raw emails never leave your site).
- The faces are AI-generated people who don't exist, served by the free [Narkis placeholder avatar API](https://narkis.ai/developers/avatars).

## Install

Download the zip from [Releases](https://github.com/photo-intelligence-lab/narkis-avatars-wp/releases), then Plugins > Add New > Upload Plugin. Pending review on wordpress.org.

## Not on WordPress?

Use the API directly: `<img src="https://narkis.ai/api/avatar?seed=alice&size=128">`. Docs and snippets: [narkis.ai/developers/avatars](https://narkis.ai/developers/avatars).

GPLv2 or later. Built by [Photo Intelligence Lab](https://github.com/photo-intelligence-lab) at [Narkis.ai](https://narkis.ai).
