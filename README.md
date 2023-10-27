# Laravel Screenshot Service

This is a Laravel-based microservice for capturing website screenshots using the `spatie/browsershot` package. It provides API endpoints to take snapshots of websites by URL or by rendering provided HTML with Tailwind CSS.

## Features

-   Capture screenshots from a URL.
-   Render HTML with Tailwind CSS and capture screenshots.
-   Token-based authentication using Laravel Sanctum.

## Requirements

-   PHP >= 7.3
-   Composer
-   Node & npm
-   Puppeteer (for `spatie/browsershot`)

## Installation

1.  **Clone the Repository:**

    ```sh
    git clone https://github.com/thedevdojo/screenshot.git
    cd screenshot
    ```

2.  **Install Dependencies:**

    ```sh
    composer install
    npm install
    ```

3.  **Set up Environment Variables:**

    Copy the `.env.example` file to a new file named `.env` and update the necessary configuration settings, including database and API configuration.

4.  **Run Database Migrations:**

    ```sh
    php artisan migrate
    ```

5.  **Start the Server:**

    ```sh
    php artisan serve
    ```


## Usage

### Endpoints:

1.  **Capture Screenshot from URL:**

    **Endpoint:** `/api/snap-from-url`

    **Method:** `POST`

    **Headers:**

    -   `Content-Type: application/json`
    -   `Authorization: Bearer YOUR_TOKEN`

    **Payload:**

    ```json
    {   "url": "https://www.example.com" }
    ```

2.  **Render HTML with Tailwind and Capture Screenshot:**

    **Endpoint:** `/api/snap-from-html`

    **Method:** `POST`

    **Headers:**

    -   `Content-Type: application/json`
    -   `Authorization: Bearer YOUR_TOKEN`

    **Payload:**

    ```json
    {   "html": "<div class='bg-blue-500 text-white p-4'>Hello, Tailwind!</div>" }
    ```


### Authentication:

You need to authenticate your requests using Laravel Sanctum. Please refer to the Laravel Sanctum documentation for generating and managing tokens.

## Examples

1.  **Endpoint for Taking a Snapshot of a URL**:

    Before you can make a request to this endpoint, ensure you have an authentication token. Assuming you've implemented Laravel Sanctum's token authentication, you would first get a token and then include it in the headers for authentication.

    First, get a token:

    ```bash
    curl -X POST -H "Content-Type: application/json" -d '{"email":"test@example.com", "password":"testpassword"}' https://screenshot.devdojo.com/api/login
    ```

    Export the token as an env var:

    ```bash
    export API_TOKEN="YOUR_API_TOKEN_HERE"
    ```

    Then make a request to the endpoint:

    ```bash
    curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $API_TOKEN" -d '{"url":"https://www.example.com"}' https://screenshot.devdojo.com/api/snap-from-url --output screenshot.png
    ```

    This will save the screenshot as `screenshot.png` in your current directory.

2.  **Endpoint for Rendering HTML with Tailwind and Taking a Screenshot**:

    Here's how you would send an HTML snippet to be rendered and then captured:

    ```bash
    curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $API_TOKEN" -d '{"html":"<div class=\"bg-blue-500 text-white p-4\">Hello, Tailwind!</div>"}' https://screenshot.devdojo.com/api/snap-from-html --output rendered_screenshot.png
    ```

    This will save the rendered screenshot as `rendered_screenshot.png` in your current directory.

## Contributing

Contributions are welcome! Please feel free to submit a pull request.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
