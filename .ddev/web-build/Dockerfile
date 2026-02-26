ARG BASE_IMAGE
FROM $BASE_IMAGE

ENV EXTENSION_KEY "rte_ckeditor_image"
ENV DDEV_SITENAME "t12-rte-ckeditor-image"
ENV EXTENSION_TITLE "RTE CKEditor Image"
ENV EXTENSION_DESCRIPTION "Image support in CKEditor for the TYPO3 ecosystem"
ENV GITHUB_URL "https://github.com/netresearch/t3x-rte_ckeditor_image"

# Copy landing page template (uses PHP to dynamically get git info)
RUN rm -f /var/www/html/index.html
COPY index.php /var/www/html/index.php

# Setup TYPO3 v12 placeholder pages
RUN mkdir -p /var/www/html/v12/public/typo3
RUN echo "<h1>Perform this first</h1> <code>ddev install-v12</code>" > /var/www/html/v12/public/index.html
RUN echo "<h1>Perform this first</h1> <code>ddev install-v12</code>" > /var/www/html/v12/public/typo3/index.html

ARG uid
ARG gid
RUN chown -R $uid:$gid /var/www/html
