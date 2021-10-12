<h1 style="text-align:center;">REST API Comment</h1>

* Contributors: joaquiminteresting
* Tags: wp, rest, api, rest api, comment, json
* Requires at least: 4.7.0
* Tested up to: 5.8.1
* Requires PHP: 7.0
* Stable tag: trunk
* License: GPLv2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html

REST API Comment adds in the 'Comment creation' function to the Wordpress REST API.

# Description 

If you wish to 'Create comments' using REST API, *without* exposing Administrator credentials to the Front-End application, you are at the right place. Since WordPress 4.7, REST API was natively included in WordPress. 

In order to 'Create a comment' , the authentication for a user with 'Administrator' role is required. While this is a deliberately done for security reasons, such implementation makes it very hard for Front-End applications to implement a simple 'Post Comment' or 'Reply Comment' function.

This plugin fulfils such requirement by extending the existing WordPress REST API endpoints.

## Requirements

**Minimum PHP version: 7.0**

**Minimum Wordpress version: 4.7.0**

## Installation

Download the this repository and install it like any other WordPress plugin.

Or clone this repo into your WordPress installation into the wp-content/plugins folder.

After the installation activate the plugin through the 'Plugins' menu in WordPress

## Endpoint

When this plugin is installed one new endpoint is added to the  **wp/v2** namespace.


| Endpoint                              | HTTP Verb | Permalinks |          
| ------------------------------------- | --------- | ---------- |
| */wp-json/wp/v2/comments/create       | POST      |  enabled   |
| */?rest_route=/wp/v2/comments/create  | POST      |  disabled  |


## Usage

### Create a Comment

To create a comment using REST API, send a `POST` request to:
> `/wp-json/wp/v2/comments/create` - if permalinks is enabled on your wordpress website.

Or

> `/?rest_route=/wp/v2/comments/create` if permalinks is not enabled on your wordpress website.

With a **JSON body**, as shown bellow:

```Json
{
	"post": "Post ID",
	"author_name": "Comment Author's name",
	"author_name": "Comment Author's email",
	"content": "Comment content"
}
```

The **content** may also be send as an object:

```Json
{
	"post": "Post ID",
	"author_name": "Comment Author's name",
	"author_name": "Comment Author's email",
	"content": {
        "raw":"Comment content"
    }
}
```

Set header to: 

```
content-type: application/json
```
If successful, you should receive a response with the data of the created comment:

```Json
{
  "id": "[comment id]",
  "status": "[comment status]",
  "message":"[server response message]"
}
```

In response header the  status code should be:

```Http
HTTP 201 Created
```

### Reply a comment

To reply a comment follow you just need to add the field **parent** for the parent comment to the **JSON body**

```Json
{
	"post": "Post ID",
	"author_name": "Comment Author's name",
	"author_name": "Comment Author's email",
	"content": "Comment content",
    "parent":"Comment parent ID"
}
```
> Note: Ensure the **parent** is a comment id that belongs to the post informed in the field **post**. The comment parent post id must match the post id otherwise the following error will be shown:

```Json
{
  "code": "rest_post_mismatch_parent_post_id",
  "message": "Post ID and Parent post ID does not match",
  "data": {
    "status": 400
  }
}
```

## Frequently Asked Questions

### Why do I need REST API Comments? 
If you're planning on using your WordPress news website/blog as a Backend, and you're consuming RESTful api, you'll most probably need to **Create comments** and **Reply comments** via REST API. This is precisely what this plugin does.

### Is it secure?
Great question! For the time being, this plugin just provides the same experience any wordpress site provides by default witch is allowing any one to comment a post requiring basic infos such as: name, email address and the content, without authentication. All secure were followed based on the wordpress core code.

### There's a bug, what do I do? 
Please create a ticket on the [support team](mailto:sopport@appsdabanda.com) or open an issue in [github repository](https://github.com/JoaquimInteresting/wp-rest-comment). We'll get back to you ASAP.

## Screenshots

An sample REST API POST request using [REST API Comment](https://github.com/JoaquimInteresting/rest-api-comment.api).

<img src="./assets/screenshot-1.png">

## Changelog

### 1.0.1

* Now it requires at least wordpress version 4.7
* Response was updated
* README was Updated
* Bug fixed
### 1.0.0

* Initial Release 
* Create comment
* Reply comment 

## Upgrade Notice

Nothing to worry! 

## Contact 

If there is any thing to say about the plugin fill free to [contact us](mailto:sopport@appsdabanda.com). We'll to get in touch.
## License
[GPLv2](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
