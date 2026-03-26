# Use official Node.js image
FROM node:18

# Create app directory
WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm install

# Copy all files
COPY . .

# Expose port (Render uses 10000 or env PORT)
EXPOSE 3000

# Start the server
CMD ["npm", "start"]
