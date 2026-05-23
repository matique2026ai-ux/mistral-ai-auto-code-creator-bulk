import { Request, Response } from 'express';
import { User } from '../models/User';
import jwt from 'jsonwebtoken';
import bcrypt from 'bcryptjs';
import logger from '../utils/logger';

class AuthController {
  async login(req: Request, res: Response) {
    try {
      const { email, password } = req.body;
      
      // Validation
      if (!email || !password) {
        return res.status(400).json({ error: 'Email and password are required' });
      }
      
      // Find user
      const user = await User.findOne({ where: { email } });
      if (!user) {
        logger.warn(`Login attempt with non-existent email: ${email}`);
        return res.status(401).json({ error: 'Invalid credentials' });
      }
      
      // Check password
      const isMatch = await bcrypt.compare(password, user.password_hash);
      if (!isMatch) {
        logger.warn(`Failed login attempt for user: ${email}`);
        return res.status(401).json({ error: 'Invalid credentials' });
      }
      
      // Generate JWT token
      const token = jwt.sign(
        { id: user.id, role: user.role },
        process.env.JWT_SECRET!,
        { expiresIn: '1h' }
      );
      
      logger.info(`User logged in: ${email}`);
      res.json({ token, user: { id: user.id, email: user.email, role: user.role } });
    } catch (error) {
      logger.error(`Login error: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
  
  async register(req: Request, res: Response) {
    try {
      const { email, password } = req.body;
      
      // Validation
      if (!email || !password) {
        return res.status(400).json({ error: 'Email and password are required' });
      }
      
      // Check if user exists
      const existingUser = await User.findOne({ where: { email } });
      if (existingUser) {
        return res.status(400).json({ error: 'Email already in use' });
      }
      
      // Hash password
      const salt = await bcrypt.genSalt(10);
      const passwordHash = await bcrypt.hash(password, salt);
      
      // Create user
      const user = await User.create({
        email,
        password_hash: passwordHash,
        role: 'user'
      });
      
      logger.info(`New user registered: ${email}`);
      res.status(201).json({ message: 'User created successfully' });
    } catch (error) {
      logger.error(`Registration error: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
}

export default new AuthController();