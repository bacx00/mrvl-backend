# 🎮 MARVEL RIVALS FRONTEND - LIVE DATA INTEGRATION GUIDE

## 🎯 **CURRENT BACKEND DATA (READY FOR FRONTEND):**

### **✅ Teams Available:**
- **17 Professional Teams** with realistic ratings (1089-2387)
- **Rankings**: Luminosity Gaming (2387) → Yoinkada (1089)
- **Divisions**: Celestial, Vibranium, Diamond, Platinum, Gold
- **Complete Data**: earnings, achievements, social media, streaks

### **✅ Players Available:**
- **116 Total Players** (102 team + 14 free agents)
- **Roles**: Tank, Duelist, Support (6 per team)
- **Marvel Heroes**: Iron Man, Spider-Man, Thor, Hulk, Storm, etc.
- **Complete Profiles**: ratings, earnings, bios, ages

---

## 🔧 **FRONTEND FIXES NEEDED:**

### **1. 🖼️ IMAGE SYSTEM FIXES**

**Current Issue**: 404 errors for team logos
**Solution**: Enhanced fallback system

```javascript
// Fix: /frontend/src/utils/imageUtils.js
export const getTeamLogoUrl = (team) => {
  if (!team) {
    return 'https://images.unsplash.com/photo-1635805737707-575885ab0820?w=80&h=80&fit=crop&crop=center';
  }

  // If team has logo path, try it first
  if (team.logo && !team.logo.includes('undefined')) {
    const logoUrl = team.logo.startsWith('http') 
      ? team.logo 
      : `${import.meta.env.VITE_API_URL || 'https://staging.mrvl.net'}/storage/${team.logo}`;
    
    return logoUrl;
  }

  // Marvel Rivals team logo fallbacks based on team name
  const teamLogos = {
    'Luminosity Gaming': 'https://images.unsplash.com/photo-1614680376593-902f74cf0d41?w=80&h=80&fit=crop',
    'Fnatic': 'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=80&h=80&fit=crop',
    'OG': 'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?w=80&h=80&fit=crop',
    'Sentinels': 'https://images.unsplash.com/photo-1511512578047-dfb367046420?w=80&h=80&fit=crop',
    '100 Thieves': 'https://images.unsplash.com/photo-1486312338219-ce68e2c6b7d6?w=80&h=80&fit=crop',
    'SHROUD-X': 'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=80&h=80&fit=crop',
    'Team Nemesis': 'https://images.unsplash.com/photo-1555952494-efd681c7e3f9?w=80&h=80&fit=crop',
    'FlyQuest': 'https://images.unsplash.com/photo-1574125343397-d6d7b4a96b68?w=80&h=80&fit=crop',
    'Rival Esports': 'https://images.unsplash.com/photo-1552820728-8b83bb6b773f?w=80&h=80&fit=crop'
  };

  return teamLogos[team.name] || 'https://images.unsplash.com/photo-1635805737707-575885ab0820?w=80&h=80&fit=crop&crop=center';
};

// Enhanced player avatar system
export const getPlayerAvatarUrl = (player) => {
  if (!player) {
    return 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=80&h=80&fit=crop';
  }

  if (player.avatar && !player.avatar.includes('undefined')) {
    const avatarUrl = player.avatar.startsWith('http') 
      ? player.avatar 
      : `${import.meta.env.VITE_API_URL || 'https://staging.mrvl.net'}/storage/${player.avatar}`;
    
    return avatarUrl;
  }

  // Marvel hero-based avatars
  const heroAvatars = {
    'Iron Man': 'https://images.unsplash.com/photo-1608889476561-6242cfdbf622?w=80&h=80&fit=crop',
    'Spider-Man': 'https://images.unsplash.com/photo-1635758072405-94c2b3c6ac3a?w=80&h=80&fit=crop',
    'Thor': 'https://images.unsplash.com/photo-1566479179817-c69b1b0f44a8?w=80&h=80&fit=crop',
    'Hulk': 'https://images.unsplash.com/photo-1564564321837-a57b7070ac4f?w=80&h=80&fit=crop',
    'Storm': 'https://images.unsplash.com/photo-1518893883800-45cd0954574b?w=80&h=80&fit=crop',
    'Mantis': 'https://images.unsplash.com/photo-1503454537195-1dcabb73ffb9?w=80&h=80&fit=crop'
  };

  return heroAvatars[player.main_hero] || 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=80&h=80&fit=crop';
};
```

### **2. 🏆 DIVISION SYSTEM DISPLAY**

```javascript
// Add: /frontend/src/utils/divisionUtils.js
export const getDivisionInfo = (rating) => {
  if (rating >= 2500) return { name: 'Eternity', color: '#FFD700', icon: '👑' };
  if (rating >= 2200) return { name: 'Celestial', color: '#E6E6FA', icon: '⭐' };
  if (rating >= 1900) return { name: 'Vibranium', color: '#C0C0C0', icon: '💎' };
  if (rating >= 1600) return { name: 'Diamond', color: '#87CEEB', icon: '💠' };
  if (rating >= 1300) return { name: 'Platinum', color: '#70C2C2', icon: '🔷' };
  if (rating >= 1000) return { name: 'Gold', color: '#FFD700', icon: '🥇' };
  return { name: 'Silver', color: '#C0C0C0', icon: '🥈' };
};

export const DivisionBadge = ({ rating, className = "" }) => {
  const division = getDivisionInfo(rating);
  
  return (
    <span className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium ${className}`}
          style={{ backgroundColor: division.color + '20', color: division.color }}>
      <span className="mr-1">{division.icon}</span>
      {division.name}
    </span>
  );
};
```

### **3. 📊 ENHANCED TEAM DISPLAY COMPONENTS**

```javascript
// Update: /frontend/src/components/TeamCard.js
import { getTeamLogoUrl } from '../utils/imageUtils';
import { DivisionBadge } from '../utils/divisionUtils';

const TeamCard = ({ team, onClick }) => {
  return (
    <div className="bg-gray-900 rounded-lg p-4 border border-gray-700 hover:border-blue-500 transition cursor-pointer"
         onClick={() => onClick && onClick(team)}>
      
      {/* Team Header */}
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center space-x-3">
          <img 
            src={getTeamLogoUrl(team)} 
            alt={team.name}
            className="w-12 h-12 rounded-full object-cover"
            onError={(e) => {
              e.target.src = 'https://images.unsplash.com/photo-1635805737707-575885ab0820?w=80&h=80&fit=crop&crop=center';
            }}
          />
          <div>
            <h3 className="text-white font-bold text-lg">{team.name}</h3>
            <p className="text-gray-400 text-sm">{team.short_name} • {team.region}</p>
          </div>
        </div>
        <div className="text-right">
          <p className="text-2xl font-bold text-blue-400">{team.rating}</p>
          <p className="text-gray-400 text-sm">#{team.rank}</p>
        </div>
      </div>

      {/* Division Badge */}
      <div className="mb-3">
        <DivisionBadge rating={team.rating} />
      </div>

      {/* Team Stats */}
      <div className="grid grid-cols-3 gap-2 text-center">
        <div>
          <p className="text-white font-semibold">{team.record || '0-0'}</p>
          <p className="text-gray-400 text-xs">Record</p>
        </div>
        <div>
          <p className="text-white font-semibold">{team.earnings || '$0'}</p>
          <p className="text-gray-400 text-xs">Earnings</p>
        </div>
        <div>
          <p className="text-white font-semibold">{team.streak || 'N/A'}</p>
          <p className="text-gray-400 text-xs">Streak</p>
        </div>
      </div>

      {/* Quick Player Count */}
      <div className="mt-3 pt-3 border-t border-gray-700">
        <p className="text-gray-400 text-sm">
          {team.player_count || 6} Players • {team.country || team.region}
        </p>
      </div>
    </div>
  );
};
```

### **4. 👥 ENHANCED PLAYER COMPONENTS**

```javascript
// Update: /frontend/src/components/PlayerCard.js
import { getPlayerAvatarUrl } from '../utils/imageUtils';

const PlayerCard = ({ player, showTeam = false }) => {
  const roleColors = {
    'Tank': 'bg-blue-600',
    'Duelist': 'bg-red-600', 
    'Support': 'bg-green-600'
  };

  return (
    <div className="bg-gray-900 rounded-lg p-4 border border-gray-700">
      <div className="flex items-center space-x-3">
        {/* Player Avatar */}
        <img 
          src={getPlayerAvatarUrl(player)}
          alt={player.name}
          className="w-16 h-16 rounded-full object-cover"
          onError={(e) => {
            e.target.src = 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=80&h=80&fit=crop';
          }}
        />
        
        <div className="flex-1">
          {/* Player Name & Role */}
          <div className="flex items-center justify-between">
            <h3 className="text-white font-bold">{player.name || player.username}</h3>
            <span className={`px-2 py-1 rounded text-xs text-white ${roleColors[player.role] || 'bg-gray-600'}`}>
              {player.role}
            </span>
          </div>
          
          {/* Player Details */}
          <p className="text-gray-400 text-sm">{player.real_name}</p>
          
          {/* Hero & Rating */}
          <div className="flex items-center justify-between mt-2">
            <div>
              <p className="text-blue-400 text-sm font-medium">{player.main_hero}</p>
              {showTeam && player.team_name && (
                <p className="text-gray-500 text-xs">{player.team_name}</p>
              )}
            </div>
            <div className="text-right">
              <p className="text-white font-bold">{player.rating}</p>
              <p className="text-gray-400 text-xs">Rating</p>
            </div>
          </div>
          
          {/* Earnings */}
          {player.earnings && (
            <p className="text-green-400 text-sm mt-1">{player.earnings}</p>
          )}
        </div>
      </div>
    </div>
  );
};
```

### **5. 🎮 MATCH BROADCAST FIX**

```javascript
// Fix: /frontend/src/pages/MatchDetailPage.js
// Add this in the component where broadcast data is used:

const MatchDetailPage = ({ matchId }) => {
  const [match, setMatch] = useState(null);
  
  useEffect(() => {
    const fetchMatch = async () => {
      try {
        const response = await api.get(`/matches/${matchId}`);
        const matchData = response.data;
        
        // Ensure broadcast data exists
        if (!matchData.broadcast) {
          matchData.broadcast = {
            stream: 'https://twitch.tv/marvelrivals',
            vod: null,
            viewers: 0,
            languages: ['en']
          };
        }
        
        setMatch(matchData);
      } catch (error) {
        console.error('Error fetching match:', error);
      }
    };
    
    fetchMatch();
  }, [matchId]);

  // Safe access to broadcast data
  const streamUrl = match?.broadcast?.stream || 'https://twitch.tv/marvelrivals';
  const viewers = match?.broadcast?.viewers || 0;
  
  return (
    <div>
      {/* Match broadcast section */}
      <div className="bg-gray-900 rounded-lg p-4">
        <h3 className="text-white font-bold mb-2">Live Stream</h3>
        <a href={streamUrl} target="_blank" rel="noopener noreferrer"
           className="text-blue-400 hover:text-blue-300">
          Watch Live • {viewers.toLocaleString()} viewers
        </a>
      </div>
      
      {/* Rest of match detail content */}
    </div>
  );
};
```

---

## 🚀 **IMPLEMENTATION CHECKLIST:**

### **Phase 1: Core Fixes (Priority)**
- [ ] Update imageUtils.js with team-specific fallbacks
- [ ] Add division system with badges
- [ ] Fix broadcast data handling in match pages
- [ ] Update TeamCard component with new data

### **Phase 2: Enhanced Display**
- [ ] Implement PlayerCard with hero specializations
- [ ] Add earnings and achievements display
- [ ] Create division ranking pages
- [ ] Add free agents page

### **Phase 3: Professional Polish**
- [ ] Team pages with full rosters
- [ ] Player detail pages with stats
- [ ] Hero composition analytics
- [ ] Social media integration

---

## 📱 **EXPECTED RESULTS:**

After implementing these fixes:
- ✅ **17 teams** display with realistic ratings and divisions
- ✅ **116 players** show with Marvel hero specializations
- ✅ **Professional design** with proper fallback images
- ✅ **No more 404 errors** or missing data
- ✅ **Complete Marvel Rivals context** throughout the platform

**The platform will look like a professional esports site ready for the Marvel Rivals community!** 🎮⚡