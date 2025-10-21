ARG BASE_IMAGE
FROM $BASE_IMAGE

ENV EXTENSION_KEY "rte_ckeditor_image"
ENV DDEV_SITENAME "rte-ckeditor-image"

RUN echo "<html><head><style>h2 {display:inline-block;margin-right:0.5em;vertical-align:middle;}</style></head>" > /var/www/html/index.html
RUN echo "<body><h1>EXT:${EXTENSION_KEY} Dev Environments</h1><ul>" >> /var/www/html/index.html
RUN echo "<li><h2>TYPO3 11.5 LTS</h2><a href=\"https://v11.${DDEV_SITENAME}.ddev.site/\">Frontend</a> | <a href=\"https://v11.${DDEV_SITENAME}.ddev.site/typo3/\">Backend</a></li>" >> /var/www/html/index.html
RUN echo "<li><h2>TYPO3 12.4 LTS</h2><a href=\"https://v12.${DDEV_SITENAME}.ddev.site/\">Frontend</a> | <a href=\"https://v12.${DDEV_SITENAME}.ddev.site/typo3/\">Backend</a></li>" >> /var/www/html/index.html
RUN echo "<li><h2>TYPO3 13.4 LTS</h2><a href=\"https://v13.${DDEV_SITENAME}.ddev.site/\">Frontend</a> | <a href=\"https://v13.${DDEV_SITENAME}.ddev.site/typo3/\">Backend</a></li>" >> /var/www/html/index.html
RUN echo "</ul>" >> /var/www/html/index.html
RUN echo "<hr>" >> /var/www/html/index.html
RUN echo "<h3>TYPO3 Backend</h3><ul><li><b>User:</b> <code>admin</code></li><li><b>Password:</b> <code>Password:joh316</code> (also Install Tool)</li></ul>" >> /var/www/html/index.html
RUN echo "<hr>" >> /var/www/html/index.html
RUN echo "<h3>Documentation</h3><ul><li>First, perform <code>$ ddev docs</code> on CLI</li><li><b>Then visit URL:</b> https://docs.${DDEV_SITENAME}.ddev.site/</li></ul>" >> /var/www/html/index.html
RUN echo "</body></html>" >> /var/www/html/index.html


RUN mkdir -p /var/www/html/v11/public/typo3
RUN echo "<h1>Perform this first</h1> <code>ddev install-v11</code>" > /var/www/html/v11/public/index.html
RUN echo "<h1>Perform this first</h1> <code>ddev install-v11</code>" > /var/www/html/v11/public/typo3/index.html
RUN mkdir -p /var/www/html/v12/public/typo3
RUN echo "<h1>Perform this first</h1> <code>ddev install-v12</code>" > /var/www/html/v12/public/index.html
RUN echo "<h1>Perform this first</h1> <code>ddev install-v12</code>" > /var/www/html/v12/public/typo3/index.html
RUN mkdir -p /var/www/html/v13/public/typo3
RUN echo "<h1>Perform this first</h1> <code>ddev install-v13</code>" > /var/www/html/v13/public/index.html
RUN echo "<h1>Perform this first</h1> <code>ddev install-v13</code>" > /var/www/html/v13/public/typo3/index.html

ARG uid
ARG gid
RUN chown -R $uid:$gid /var/www/html
