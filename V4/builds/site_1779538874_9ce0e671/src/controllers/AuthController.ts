import { Request, Response } from 'express';
import { User } from '../models/User';
import jwt from 'jsonwebtoken';
import bcrypt from 'bcryptjs';
import logger from '../utils/logger';

class AuthController {
  static async login(req: Request, res: Response) {
    try {
      const { email, password } = req.body;
      
      if (!email || !password) {
        return res.status(400).json({ error: 'Email and password are required' });
      }
      
      const user = await User.findOne({ where: { email } });
      
      if (!user) {
        logger.warn(`Login attempt with non-existent email: ${email}`);
        return res.status(401).json({ error: 'Invalid credentials' });
      }
      
      const isPasswordValid = await bcrypt.compare(password, user.password_hash);
      
      if (!isPasswordValid) {
        logger.warn(`Invalid password attempt for email: ${email}`);
        return res.status(401).json({ error: 'Invalid credentials' });
      }
      
      const token = jwt.sign(
        { id: user.id, role: user.role },
        process.env.JWT_SECRET!,
        { expiresIn: '1h' }
      );
      
      logger.info(`User logged in: ${user.id}`);
      
      res.json({ token, user: { id: user.id, email: user.email, role: user.role } });
    } catch (error) {
      logger.error(`Login error: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
  
  static async register(req: Request, res: Response) {
    try {
      const { email, password } = req.body;
      
      if (!email || !password) {
        return res.status(400).json({ error: 'Email and password are required' });
      }
      
      const existingUser = await User.findOne({ where: { email } });
      
      if (existingUser) {
        return res.status(400).json({ error: 'Email already in use' });
      }
      
      const passwordHash = await bcrypt.hash(password, 10);
      
      const user = await User.create({ email, password_hash: passwordHash });
      
      logger.info(`New user registered: ${user.id}`);
      
      res.status(201).json({ id: user.id, email: user.email });
    } catch (error) {
      logger.error(`Registration error: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
}

export default AuthController;