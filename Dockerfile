# ----------------------------
# Stage 1: Composer Builder
# ----------------------------
FROM composer:2.5 AS builder
WORKDIR /app

# Copy composer files
COPY composer.json ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ----------------------------
# Stage 2: Azure-optimized PHP-FPM with Nginx
# ----------------------------
FROM mcr.microsoft.com/appsvc/php:8.3-fpm_20251016.4.tuxprod

# Avoid interactive prompts
ENV DEBIAN_FRONTEND=noninteractive \
    PORT=80 \
    WEBSITES_PORT=80 \
    APPSETTING_WEBSITES_PORT=80

# Install additional system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    wget unzip jq curl procps net-tools \
    libnss3 libgconf-2-4 libxi6 libgtk-3-0 \
    libx11-xcb1 libxcomposite1 libxdamage1 libxrandr2 \
    xvfb \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Google Chrome
RUN wget -qO /tmp/google-chrome.deb https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb && \
    apt-get update && \
    apt-get install -y /tmp/google-chrome.deb && \
    rm -rf /tmp/google-chrome.deb

# Install ChromeDriver
RUN JSON_URL="https://googlechromelabs.github.io/chrome-for-testing/last-known-good-versions-with-downloads.json" && \
    DOWNLOAD_URL=$(curl -sSL $JSON_URL | jq -r '.channels.Stable.downloads.chromedriver[] | select(.platform == "linux64") | .url') && \
    wget -O /tmp/chromedriver.zip "$DOWNLOAD_URL" && \
    unzip /tmp/chromedriver.zip -d /tmp/ && \
    mv /tmp/chromedriver-linux64/chromedriver /usr/local/bin/ && \
    chmod +x /usr/local/bin/chromedriver && \
    rm -rf /tmp/*

# Install Firefox
RUN apt-get -y install firefox-esr

# Install Geckodriver
RUN LATEST_VERSION=$(curl -s https://api.github.com/repos/mozilla/geckodriver/releases/latest | jq -r '.tag_name') && \
    echo "Downloading Geckodriver version: $LATEST_VERSION" && \
    wget -q "https://github.com/mozilla/geckodriver/releases/download/${LATEST_VERSION}/geckodriver-${LATEST_VERSION}-linux64.tar.gz" && \
    tar -xzf geckodriver-${LATEST_VERSION}-linux64.tar.gz && \
    chmod +x geckodriver && \
    mv geckodriver /usr/local/bin/ && \
    rm geckodriver-${LATEST_VERSION}-linux64.tar.gz

# Create Firefox profile directory with correct ownership
RUN mkdir -p /tmp/firefox-profiles && \
    chmod -R 777 /tmp/firefox-profiles && \
    chown -R www-data:www-data /tmp/firefox-profiles

    # Verify installations
RUN echo "Chrome version:" && google-chrome --version && \
    echo "ChromeDriver version:" && chromedriver --version \
    echo "Firefox version:" && firefox --version && \
    echo "Geckodriver version:" && geckodriver --version

# Create directories and set proper permissions
RUN mkdir -p /tmp/chrome-profiles && \
    mkdir -p /tmp/www-data && \
    mkdir -p /home/LogFiles && \
    chmod -R 777 /tmp/chrome-profiles /tmp/www-data /home/LogFiles && \
    chown -R www-data:www-data /tmp/www-data

# Fix home directory for www-data user
RUN usermod -d /tmp/www-data www-data

# Copy application files from builder to Azure directory
COPY --from=builder /app/vendor /home/site/wwwroot/vendor
COPY src/ /home/site/wwwroot/
COPY src/ /var/www/wwwroot/
COPY composer.json /home/site/wwwroot/

# Set proper permissions for Azure directory
RUN chown -R www-data:www-data /home/site/wwwroot && \
    chmod -R 755 /home/site/wwwroot

# Copy custom PHP configuration
COPY php-azure.ini /usr/local/etc/php/conf.d/999-custom.ini

# Create startup script for Chrome profile cleanup
COPY startup.sh /startup.sh
RUN chmod +x /startup.sh

WORKDIR /home/site/wwwroot

# Use the existing Azure startup mechanism with our customizations
CMD ["/startup.sh"]