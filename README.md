# FAQreator

**FAQreator** is a WordPress plugin that automatically generates frequently asked questions (FAQs) for your posts using the OpenAI API. Perfect for content creators looking to enhance audience comprehension and improve their page SEO.

## 📦 Features

- Automatic generation of FAQs based on post title and summary.
- Integration with OpenAI language model.
- Dynamic relationship between posts and FAQs.
- Support for custom fields via ACF (Advanced Custom Fields).
- Admin interface to configure API, model, parameters, and error messages.
- Shortcode to display FAQs on the frontend.
- Internationalization support with `faqreator` domain.

## 🔧 Requirements

- WordPress 5.2 or higher  
- PHP 7.2 or higher  
- A valid OpenAI API key

## 🚀 Installation

1. Upload the plugin to the `/wp-content/plugins/faqreator` folder or install directly via WordPress dashboard.
2. Activate the plugin.
3. Go to **Settings > FAQreator** and fill in the required fields:
   - OpenAI API key
   - Authentication token
   - Post type to be analyzed
   - Post type for questions
   - Number of FAQs to generate
   - Model, temperature, tokens, timeout
   - Custom error messages

## 🧠 How it works

When accessing the REST route `/wp-json/faqreator/v1/generate-faqs/` with valid `post_id` and `token`, the plugin collects the title and summary (or the first 400 characters) of the post and sends it to OpenAI.

The response with questions and answers is saved as posts of the defined type (e.g., `question`), linked to the original post (e.g., `post`) by a relational field.

## 🧾 Shortcode

Use the shortcode below inside any singular post to display associated FAQs:

[faqreator]

## 🔁 Manual trigger (via code)

You can trigger FAQ generation manually:

do_action( 'faqreator_generate_faq_event', $post_id, 'your_token' );

## 🛡 Security

Requests are protected by an authentication token defined in the settings screen. Without the correct token, access to the generation route will be denied (`401 Unauthorized`).

## 🗣 Translation

The plugin is ready for translation and uses the `__()` and `esc_html__()` functions with the `faqreator` text domain.

## ✍️ Author

**Rafy**  
https://rafy.com.br

## 📜 License

GPL v2 or later  
http://www.gnu.org/licenses/gpl-2.0.txt