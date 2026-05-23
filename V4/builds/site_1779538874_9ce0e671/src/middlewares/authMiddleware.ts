import { Request, Response, NextFunction } from 'express';
import jwt from 'jsonwebtoken';
import logger from '../utils/logger';

const authMiddleware = (req: Request, res: Response, next: NextFunction) => {
  try {
    const token = req.header('Authorization')?.replace('Bearer ', '');
    
    if (!token) {
      return res.status(401).json({ error: 'No token provided' });
    }
    
    const decoded = jwt.verify(token, process.env.JWT_SECRET!);
    
    (req as any).user = decoded;
    
    next();
  } catch (error) {
    logger.error(`Authentication error: ${error}`);
    res.status(401).json({ error: 'Invalid token' });
  }
};

export default authMiddleware;