FROM node:20-alpine

# Set working directory
WORKDIR /app

# Copy package files and install dependencies
COPY package.json package-lock.json* ./
RUN npm install

# The node_modules and app code will be mounted via docker-compose volume for development.
# We expose the Vite port
EXPOSE 5173

# The command is defined in docker-compose.yml, but we can set a default
CMD ["npm", "run", "dev", "--", "--host"]
