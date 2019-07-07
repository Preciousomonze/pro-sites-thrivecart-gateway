# pro-sites Thrivecart Gateway
A thrivecart gateway for Pro Sites plugin that makes use of thrivecart webhooks for communication

## How to Put on WordPress Site
 - Download the zip file you see on the repo, and upload it the normal way you would upload any wordpress plugin.
 - Make sure you have Prosites plugin active for it to work.
 - After activation, make sure you reset your permalinks, by saving it again, so the webhook url is recognised.

## What to do on your thrivecart account
 - Copy the webhook url on prosite thrivecart settings page and past in your Thrivecart account under __Settings > API & Webhooks > Webhooks & notifications__.
 
 - Create 4 custom fields (which is the maximum thrivecart allows you to create 😪) and give them the following name attributes:
   1. _blog_id_username_
   2. _blog_name_title_
   3. _blog_period_level_
   4. _blog_activation_key_
